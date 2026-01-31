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
            'user_id'      => (int) $m->user_id,
            'content'      => $m->content ?? null,
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

    // POST /api/chatMeetUp/friendship
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

        $existing = Friendship::where(function ($q) use ($from, $to) {
                $q->where('requester_id', $from->id)->where('receiver_id', $to->id);
            })
            ->orWhere(function ($q) use ($from, $to) {
                $q->where('requester_id', $to->id)->where('receiver_id', $from->id);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'message'    => 'درخواست دوستی قبلاً ثبت شده است.',
                'friendship' => $existing,
            ], 200);
        }

        return DB::transaction(function () use ($from, $to, $data) {
            $friendship = Friendship::create([
                'requester_id' => $from->id,
                'receiver_id'  => $to->id,
                'status'       => 'pending',
            ]);

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

            $content = trim($data['content'] ?? '') ?: 'سلام! من برایت درخواست دوستی فرستادم 🙌';

            $friendKind = defined(Message::class.'::KIND_FRIEND_REQUEST')
                ? Message::KIND_FRIEND_REQUEST
                : 'friend_request';

            $message = $room->messages()->create([
                'user_id' => $from->id,
                'content' => $content,
                'kind'    => $friendKind,
            ]);

            try {
                event(new ChatMessageCreated($room->id, $message));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (friend_request msg)', [
                    'msg_id' => $message->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message'    => 'Friend request sent.',
                'friendship' => $friendship,
                'room'       => $room->load('users:id,name,email'),
                'dm_message' => $this->shapeMessage($message),
            ], 201);
        });
    }

    // POST /api/chatMeetUp/friendship/respond
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

    // GET /api/chatMeetUp/chatrooms
    public function chatrooms(Request $request)
    {
        $me = $request->user();

        $rooms = ChatRoom::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->with([
                'users:id,name,email',
                'lastMessage.user:id,name,email',
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json($rooms);
    }

    // GET /api/chatMeetUp/conversations
    public function conversations(Request $request)
    {
        $me = $request->user();

        try {
            $rooms = ChatRoom::query()
                ->where('is_private', true)
                ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
                ->with([
                    'users:id,name,email',
                    'lastMessage.user:id,name,email',
                ])
                ->orderByDesc('last_message_at')
                ->get();

            $partners = $rooms->map(function (ChatRoom $room) use ($me) {
                $partner = $room->users->firstWhere('id', '!=', $me->id);
                $lastMsg = $room->lastMessage;

                $friendship = null;
                if ($partner) {
                    $friendship = Friendship::where(function ($q) use ($me, $partner) {
                            $q->where('requester_id', $me->id)->where('receiver_id', $partner->id);
                        })
                        ->orWhere(function ($q) use ($me, $partner) {
                            $q->where('requester_id', $partner->id)->where('receiver_id', $me->id);
                        })
                        ->first();
                }

                $friendshipId  = $friendship?->id;
                $friendshipRaw = $friendship?->status;

                $friendshipStatus    = 'none';
                $friendshipDirection = null;

                if ($friendship) {
                    if ($friendship->status === 'accepted') {
                        $friendshipStatus    = 'accepted';
                        $friendshipDirection = 'mutual';
                    } elseif ($friendship->status === 'pending') {
                        if ((int) $friendship->requester_id === (int) $me->id) {
                            $friendshipStatus    = 'pending_outgoing';
                            $friendshipDirection = 'outgoing';
                        } else {
                            $friendshipStatus    = 'pending_incoming';
                            $friendshipDirection = 'incoming';
                        }
                    } else {
                        $friendshipStatus = $friendship->status;
                    }
                }

                return [
                    'id'                   => $partner?->id,
                    'first_name'           => $partner?->name ?? $partner?->email ?? 'Unknown',
                    'last_name'            => null,
                    'room_id'              => $room->id,
                    'last_message'         => $lastMsg?->content ?? '',
                    'last_message_at'      => optional($lastMsg?->created_at)->toIso8601String(),
                    'friendship_id'        => $friendshipId,
                    'friendship_raw'       => $friendshipRaw,
                    'friendship_status'    => $friendshipStatus,
                    'friendship_direction' => $friendshipDirection,
                ];
            })->values();

            return response()->json([
                'partners' => $partners,
                'groups'   => [],
            ]);
        } catch (\Throwable $e) {
            Log::error('conversations failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);
            return response()->json(['message' => 'Server Error'], 500);
        }
    }

    // GET /api/chatMeetUp/messages/{room}
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

        return response()->json([
            'chat_room_id' => (int) $chatRoom->id,
            'messages'     => $messages,
        ], 200);
    }

    // POST /api/chatMeetUp/messages/{room}
    public function postMessage(Request $request, $room)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string'],
            'kind'    => ['nullable', 'string'],
        ]);

        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->firstOrFail();

        $message = $chatRoom->messages()->create([
            'user_id' => $me->id,
            'content' => $data['content'] ?? '',
            'kind'    => $data['kind'] ?? 'text',
        ]);

        $chatRoom->update(['last_message_at' => now()]);
        $chatRoom->loadMissing('users:id');

        event(new ChatMessageCreated($chatRoom->id, $message));

        foreach ($chatRoom->users as $u) {
            if ((int) $u->id === (int) $me->id) continue;

            event(new ChatGlobalNotify((int) $u->id, [
                'type'    => 'notify',
                'room_id' => (int) $chatRoom->id,
                'message' => $this->shapeMessage($message->fresh(['user:id,name,email'])),
            ]));
        }

        return response()->json([
            'type'    => 'message',
            'message' => $this->shapeMessage($message->fresh(['user:id,name,email'])),
        ], 201);
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
