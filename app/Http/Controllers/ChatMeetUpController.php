<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Models\User;
use App\Models\Friendship;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Support\Facades\Broadcast;
use App\Events\TypingIndicator;

use App\Events\ChatMessageCreated;
use App\Events\ChatGlobalNotify;
class ChatMeetUpController extends Controller
{
    private function shapeMessage(Message $m): array
    {
        $m->loadMissing('user:id,name,email');

        return [
            'id'           => (int) $m->id,
            'chat_room_id' => (int) $m->chat_room_id,
            'room_id'      => (int) $m->chat_room_id, // ✅ Node-friendly
            'user_id'      => (int) $m->user_id,
            'sender_id'    => (int) $m->user_id,      // ✅ Node-friendly
            'content'      => $m->content ?? null,
            'text'         => $m->content ?? null,    // ✅ Node-friendly
            'kind'         => $m->kind ?? null,
            'created_at'   => optional($m->created_at)->toIso8601String(),
            'updated_at'   => optional($m->updated_at)->toIso8601String(),
            'user'         => $m->user ? [
                'id'    => (int) $m->user->id,
                'name'  => (string) $m->user->name,
                'email' => (string) $m->user->email,
            ] : null,
        ];
    }

    private function shapeRoomMembers(ChatRoom $room): array
    {
        // Node room shape: room.members[].user
        $room->loadMissing('users:id,name,email');

        $members = $room->users->map(fn ($u) => [
            'user' => [
                'id'    => (int) $u->id,
                'name'  => (string) $u->name,
                'email' => (string) $u->email,
            ],
        ])->values()->all();

        return $members;
    }

    // ✅ GET /api/chat/rooms
    public function rooms(Request $request)
    {
        return $this->chatrooms($request);
    }

    // GET /api/chatrooms (old) OR used by rooms()
    public function chatrooms(Request $request)
    {
        $me = $request->user();

        $rooms = ChatRoom::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->with([
                'users:id,name,email',
                'lastMessage.user:id,name,email',
            ])
            ->orderByDesc('updated_at') // closer to Node listRooms ordering
            ->get();

        return response()->json($rooms);
    }

    // ✅ GET /api/chat/conversations  (Node-like array)
    public function conversations(Request $request)
    {
        $me = $request->user();

        $rooms = ChatRoom::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->with([
                'users:id,name,email',
                'lastMessage.user:id,name,email',
            ])
            ->orderByDesc('updated_at')
            ->get();

        $out = $rooms->map(function (ChatRoom $r) use ($me) {
            $lastMsg = $r->lastMessage;

            $users = $r->users->map(fn ($u) => [
                'id'    => (int) $u->id,
                'name'  => (string) $u->name,
                'email' => (string) $u->email,
            ])->values();

            // ✅ match Node: kind = group|dm
            $kind = ($r->is_private ?? false) ? 'dm' : 'group';
            // اگر مدل تو is_group داره:
            // $kind = ($r->is_group ?? false) ? 'group' : 'dm';

            $is_private = ($kind === 'dm');

            $partner = null;
            if ($is_private) {
                $partner = $r->users->firstWhere('id', '!=', $me->id);
            }

            $title = $kind === 'group'
                ? ($r->name ?? null)
                : ($partner?->name ?? $partner?->email ?? null);

            return [
                'id'         => (int) $r->id,
                'kind'       => $kind,
                'is_private' => $is_private,
                'title'      => $title,
                'name'       => $title,
                'users'      => $users,

                'last_message' => $lastMsg ? [
                    'id'         => (int) $lastMsg->id,
                    'content'    => $lastMsg->content ?? null,
                    'text'       => $lastMsg->content ?? null,
                    'kind'       => $lastMsg->kind ?? 'text',
                    'user_id'    => (int) $lastMsg->user_id,
                    'created_at' => optional($lastMsg->created_at)->toIso8601String(),
                    'user'       => $lastMsg->user ? [
                        'id'    => (int) $lastMsg->user->id,
                        'name'  => (string) $lastMsg->user->name,
                        'email' => (string) $lastMsg->user->email,
                    ] : null,
                ] : null,

                'last_message_text' => $lastMsg?->content ?? '',
                'last_message_at'   => optional($lastMsg?->created_at)->toIso8601String(),
                'updated_at'        => optional($r->updated_at)->toIso8601String(),

                'isGroup'  => $kind === 'group',
                'is_group' => $kind === 'group',

                'partnerId'  => $partner?->id ? (int) $partner->id : null,
                'partner_id' => $partner?->id ? (int) $partner->id : null,

                'unread_count' => 0,
            ];
        })->values();

        return response()->json($out);
    }

    // ✅ POST /api/chat/conversations  (create DM room)
    public function createConvo(Request $request)
    {
        $me = $request->user();

        $data = $request->validate([
            'userId'     => ['nullable', 'integer', 'exists:users,id'],
            'to_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $otherId = (int) ($data['userId'] ?? $data['to_user_id'] ?? 0);

        if (!$otherId) {
            return response()->json(['message' => 'userId (or to_user_id) is required'], 422);
        }

        if ((int) $me->id === $otherId) {
            return response()->json(['message' => 'Cannot create conversation with yourself'], 422);
        }

        $other = User::find($otherId);
        if (!$other) return response()->json(['message' => 'User not found'], 404);

        return DB::transaction(function () use ($me, $other) {

            $existingRoomRow = DB::table('chat_rooms as r')
                ->join('chat_room_user as cru1', 'cru1.chat_room_id', '=', 'r.id')
                ->join('chat_room_user as cru2', 'cru2.chat_room_id', '=', 'r.id')
                ->where('r.is_private', true)
                ->where('cru1.user_id', $me->id)
                ->where('cru2.user_id', $other->id)
                ->select('r.id')
                ->first();

            if ($existingRoomRow) {
                $room = ChatRoom::find($existingRoomRow->id);
            } else {
                $room = ChatRoom::create([
                    'name'        => null,
                    'description' => null,
                    'is_private'  => true,
                    'private_key' => (string) Str::uuid(),
                ]);
                $room->users()->attach([$me->id, $other->id]);
            }

            $room->touch(); // ✅ updates updated_at
            $room->load('users:id,name,email', 'lastMessage.user:id,name,email');

            return response()->json([
                'ok'   => true,
                'room' => $room,
            ], 201);
        });
    }

    // ✅ GET /api/chat/messages/{room}
    public function getMessages(Request $request, $room)
    {
        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->first();

        if (!$chatRoom) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $messages = $chatRoom->messages()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Message $m) => $this->shapeMessage($m))
            ->values();

        // ✅ Node returns array
        return response()->json($messages, 200);
    }

    // ✅ POST /api/chat/messages/{room}
    public function postMessage(Request $request, $room)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string'],
            'text'    => ['nullable', 'string'], // ✅ Node often uses text
            'kind'    => ['nullable', 'string'],
        ]);

        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->firstOrFail();

        $content = trim((string)($data['content'] ?? $data['text'] ?? ''));
        if ($content === '') {
            return response()->json(['message' => 'INVALID_TEXT'], 400);
        }

        $message = $chatRoom->messages()->create([
            'user_id' => $me->id,
            'content' => $content,
            'kind'    => $data['kind'] ?? 'text',
        ]);

        // ✅ Node bumps updatedAt
        $chatRoom->touch();
        $chatRoom->update(['last_message_at' => now()]);
        $chatRoom->loadMissing('users:id');

        event(new ChatMessageCreated($chatRoom->id, $message));

        foreach ($chatRoom->users as $u) {
            if ((int) $u->id === (int) $me->id) continue;

            event(new ChatGlobalNotify((int) $u->id, [
                'type'    => 'notify',
                'room_id' => (int) $chatRoom->id,
                'roomId'  => (int) $chatRoom->id,
                'message' => $this->shapeMessage($message->fresh(['user:id,name,email'])),
            ]));
        }

        $payloadMsg = $this->shapeMessage($message->fresh(['user:id,name,email']));

        // ✅ Node-like response
        return response()->json([
            'ok'      => true,
            'type'    => 'message',
            'room_id' => (int) $chatRoom->id,
            'roomId'  => (int) $chatRoom->id,
            'message' => $payloadMsg,
        ], 201);
    }

    // ✅ POST /api/chat/friendship
    public function sendFriendship(Request $request)
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'content'    => ['nullable', 'string'],
        ]);

        $from = $request->user();
        $to   = User::find($data['to_user_id']);

        if (!$to) return response()->json(['message' => 'User not found'], 404);

        if ((int) $from->id === (int) $to->id) {
            return response()->json(['message' => 'نمی‌توانید خودتان را اضافه کنید.'], 422);
        }

        // existing friendship?
        $existing = Friendship::where(function ($q) use ($from, $to) {
                $q->where('requester_id', $from->id)->where('receiver_id', $to->id);
            })
            ->orWhere(function ($q) use ($from, $to) {
                $q->where('requester_id', $to->id)->where('receiver_id', $from->id);
            })
            ->first();

        // ✅ still ensure DM room like Node does
        return DB::transaction(function () use ($from, $to, $data, $existing) {

            $existingRoomRow = DB::table('chat_rooms as r')
                ->join('chat_room_user as cru1', 'cru1.chat_room_id', '=', 'r.id')
                ->join('chat_room_user as cru2', 'cru2.chat_room_id', '=', 'r.id')
                ->where('r.is_private', true)
                ->where('cru1.user_id', $from->id)
                ->where('cru2.user_id', $to->id)
                ->select('r.id')
                ->first();

            if ($existingRoomRow) {
                $room = ChatRoom::find($existingRoomRow->id);
            } else {
                $room = ChatRoom::create([
                    'name'        => null,
                    'description' => null,
                    'is_private'  => true,
                    'private_key' => (string) Str::uuid(),
                ]);
                $room->users()->attach([$from->id, $to->id]);
            }

            $text = trim($data['content'] ?? '') ?: 'سلام! من برایت درخواست دوستی فرستادم 🙌';

            $friendKind = defined(Message::class.'::KIND_FRIEND_REQUEST')
                ? Message::KIND_FRIEND_REQUEST
                : 'friend_request';

            $dm = $room->messages()->create([
                'user_id' => $from->id,
                'content' => $text,
                'kind'    => $friendKind,
            ]);

            // ✅ bump ordering fields like Node
            $room->touch();
            $room->update(['last_message_at' => now()]);

            // friendship create if not exists
            $friendship = $existing;
            $status = 200;

            if (!$existing) {
                $friendship = Friendship::create([
                    'requester_id' => $from->id,
                    'receiver_id'  => $to->id,
                    'status'       => 'pending',
                ]);
                $status = 201;
            }

            try {
                event(new ChatMessageCreated($room->id, $dm));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (friend_request msg)', [
                    'msg_id' => $dm->id,
                    'error'  => $e->getMessage(),
                ]);
            }
// ✅ also notify receiver via private user channel (like postMessage)
//    so ConversationList updates even if receiver is not in the room
try {
    event(new ChatGlobalNotify((int) $to->id, [
        'type'    => 'notify',
        'room_id' => (int) $room->id,
        'roomId'  => (int) $room->id,
        'message' => $this->shapeMessage($dm->fresh(['user:id,name,email'])),
    ]));
} catch (\Throwable $e) {
    Log::error('Broadcast failed (friend_request notify receiver)', [
        'room_id' => $room->id,
        'to_user' => $to->id,
        'error'   => $e->getMessage(),
    ]);
}

// ✅ OPTIONAL (but recommended): also notify sender
//    in case sender isn't joined to room yet (fresh load / navigation)
try {
    event(new ChatGlobalNotify((int) $from->id, [
        'type'    => 'notify',
        'room_id' => (int) $room->id,
        'roomId'  => (int) $room->id,
        'message' => $this->shapeMessage($dm->fresh(['user:id,name,email'])),
    ]));
} catch (\Throwable $e) {
    Log::error('Broadcast failed (friend_request notify sender)', [
        'room_id' => $room->id,
        'from_user' => $from->id,
        'error'   => $e->getMessage(),
    ]);
}


            $roomLoaded = $room->load('users:id,name,email');
            $roomArr = $roomLoaded->toArray();
            $roomArr['members'] = $this->shapeRoomMembers($roomLoaded); // ✅ Node-like members

            return response()->json([
                'message'    => $existing ? 'درخواست دوستی قبلاً ثبت شده است.' : 'Friend request sent.',
                'friendship' => $friendship,
                'room'       => $roomArr,
                'dm_message' => $this->shapeMessage($dm),
            ], $status);
        });
    }

    // (Optional) keep old endpoint for existing frontend flows
    public function respondFriendship(Request $request)
    {
        $me = $request->user();

        $data = $request->validate([
            'friendship_id' => ['required', 'integer', 'exists:friendships,id'],
            'action'        => ['required', Rule::in(['accept', 'reject'])],
        ]);

        $friendship = Friendship::findOrFail($data['friendship_id']);

        if ((int) $friendship->receiver_id !== (int) $me->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $friendship->status = $data['action'] === 'accept' ? 'accepted' : 'rejected';
        $friendship->save();

        return response()->json([
            'message'        => 'Friend request updated.',
            'friendship_id'  => $friendship->id,
            'friendship_raw' => $friendship->status,
        ]);
    }
// ✅ POST /api/chat/typing  (Laravel Reverb typing, exclude sender)


public function typing(Request $request)
{
    $validated = $request->validate([
        'room_id'  => 'required|integer',
        'isTyping' => 'required|boolean',
    ]);

    $roomId   = (int) $validated['room_id'];
    $userId   = (int) $request->user()->id;
    $isTyping = (bool) $validated['isTyping'];

    \Log::info('[typing] hit', [
        'roomId'   => $roomId,
        'userId'   => $userId,
        'isTyping' => $isTyping,
        'socketId' => $request->header('X-Socket-Id'),
    ]);

    $event = new TypingIndicator($roomId, $userId, $isTyping);

    // ✅ این خط کلیدی است: exclude current socket
    $event->dontBroadcastToCurrentUser();

    broadcast($event);

    return response()->json(['ok' => true]);
}


    // GET /api/debug/broadcast/{roomId}
    public function debugBroadcast(Request $request, $roomId)
    {
        $roomId = (int) $roomId;
        $me = $request->user();

        $m = Message::create([
            'chat_room_id' => $roomId,
            'user_id'      => $me->id,
            'content'      => 'debug broadcast ' . now()->toDateTimeString(),
            'kind'         => defined(Message::class.'::KIND_TEXT') ? Message::KIND_TEXT : 'text',
        ]);

        event(new ChatMessageCreated($roomId, $m));

        return response()->json(['ok' => true, 'roomId' => $roomId, 'messageId' => $m->id]);
    }
}
