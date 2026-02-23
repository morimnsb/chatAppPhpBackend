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
use App\Models\ChatRoomMember;
use App\Models\Message;

use App\Events\TypingIndicator;
use App\Events\ChatMessageCreated;
use App\Events\ChatGlobalNotify;

class ChatMeetUpController extends Controller
{
    /* ------------------------------------------------------------------
     | helpers
     * ------------------------------------------------------------------ */

    private function iso($dt): ?string
    {
        return $dt ? optional($dt)->toIso8601String() : null;
    }

    private function shapeUser(?User $u): ?array
    {
        if (!$u) return null;

        return [
            'id'    => (int) $u->id,
            'name'  => (string) ($u->name ?? ''),
            'email' => (string) ($u->email ?? ''),
        ];
    }

    private function shapeMessage(Message $m): array
    {
        $m->loadMissing('user:id,name,email');

        // Prisma columns: roomId/userId/createdAt/updatedAt
        // Node-friendly aliases included
        return [
            'id'            => (int) $m->id,

            // ✅ canonical (our standard)
            'roomId'        => (int) $m->roomId,
            'userId'        => (int) $m->userId,
            'content'       => $m->content ?? null,
            'kind'          => $m->kind ?? null,
            'createdAt'     => $this->iso($m->createdAt),
            'updatedAt'     => $this->iso($m->updatedAt),

            // ✅ Node-friendly aliases (keep frontend backward compatible)
            'chat_room_id'  => (int) $m->roomId,
            'room_id'       => (int) $m->roomId,
            'user_id'       => (int) $m->userId,
            'sender_id'     => (int) $m->userId,
            'text'          => $m->content ?? null,
            'created_at'    => $this->iso($m->createdAt),
            'updated_at'    => $this->iso($m->updatedAt),

            'user'          => $this->shapeUser($m->user),
        ];
    }

    private function membersForRoomIds(array $roomIds)
    {
        // returns groupBy(roomId)
        return ChatRoomMember::query()
            ->whereIn('roomId', $roomIds)
            ->with(['user:id,name,email'])
            ->get()
            ->groupBy('roomId');
    }

    private function lastMessageByRoomIds(array $roomIds)
    {
        // last msg per room (createdAt desc)
        return Message::query()
            ->whereIn('roomId', $roomIds)
            ->orderByDesc('createdAt')
            ->get()
            ->groupBy('roomId')
            ->map(fn ($g) => $g->first());
    }

    private function shapeRoom(ChatRoom $r, $membersGrouped, $lastByRoom, int $meId): array
    {
        $members = collect($membersGrouped[$r->id] ?? [])->map(function ($mm) {
            return [
                // canonical
                'id'      => (int) $mm->id,
                'roomId'  => (int) $mm->roomId,
                'userId'  => (int) $mm->userId,
                'createdAt' => $this->iso($mm->createdAt),

                // Node-friendly
                'room_id' => (int) $mm->roomId,
                'user_id' => (int) $mm->userId,
                'created_at' => $this->iso($mm->createdAt),

                // Node wants: members[].user
                'user'    => $this->shapeUser($mm->user),
            ];
        })->values();

        $last = $lastByRoom[$r->id] ?? null;

        // determine DM partner (if dm)
        $partnerUser = null;
        if (!(bool) $r->isGroup) {
            $partnerUser = $members
                ->map(fn ($x) => $x['user'])
                ->first(fn ($u) => (int) ($u['id'] ?? 0) !== $meId);
        }

        $title = (bool) $r->isGroup
            ? ($r->name ?? null)
            : (($partnerUser['name'] ?? null) ?: ($partnerUser['email'] ?? null));

        $kind = (bool) $r->isGroup ? 'group' : 'dm';

        return [
            // ✅ canonical
            'id'        => (int) $r->id,
            'name'      => $r->name,
            'title'     => $title,
            'kind'      => $kind,
            'isGroup'   => (bool) $r->isGroup,
            'privateKey'=> $r->privateKey,
            'createdAt' => $this->iso($r->createdAt),
            'updatedAt' => $this->iso($r->updatedAt),
            'members'   => $members,
            'lastMessage' => $last ? $this->shapeMessage($last) : null,

            // ✅ Node-friendly aliases
            'is_private' => $kind === 'dm',
            'is_group'   => $kind === 'group',
            'partnerId'  => $partnerUser['id'] ?? null,
            'partner_id' => $partnerUser['id'] ?? null,
            'last_message' => $last ? [
                'id'         => (int) $last->id,
                'content'    => $last->content ?? null,
                'text'       => $last->content ?? null,
                'kind'       => $last->kind ?? 'text',
                'user_id'    => (int) $last->userId,
                'created_at' => $this->iso($last->createdAt),
                'user'       => $this->shapeUser($last->user ?? null),
            ] : null,
            'last_message_text' => $last?->content ?? '',
            'last_message_at'   => $this->iso($last?->createdAt),
            'updated_at'        => $this->iso($r->updatedAt),

            'unread_count' => 0,
        ];
    }

    /* ------------------------------------------------------------------
     | GET /api/chat/rooms  (alias)
     * ------------------------------------------------------------------ */
    public function rooms(Request $request)
    {
        return $this->chatrooms($request);
    }

    /* ------------------------------------------------------------------
     | GET /api/chatrooms  (raw rooms list, still Node-ish)
     * ------------------------------------------------------------------ */
    public function chatrooms(Request $request)
    {
        $me = $request->user();
        if (!$me) return response()->json([], 401);

        $uid = (int) $me->id;

        $rooms = ChatRoom::query()
            ->whereHas('members', fn ($q) => $q->where('userId', $uid))
            ->orderByDesc('updatedAt')
            ->get();

        $roomIds = $rooms->pluck('id')->all();
        $membersGrouped = $this->membersForRoomIds($roomIds);
        $lastByRoom = $this->lastMessageByRoomIds($roomIds);

        $out = $rooms->map(fn (ChatRoom $r) => $this->shapeRoom($r, $membersGrouped, $lastByRoom, $uid))->values();

        return response()->json($out);
    }

    /* ------------------------------------------------------------------
     | GET /api/chat/conversations  (Node-like array)
     * ------------------------------------------------------------------ */
    public function conversations(Request $request)
    {
        // identical to chatrooms output in our standard
        return $this->chatrooms($request);
    }

    /* ------------------------------------------------------------------
     | POST /api/chat/conversations  (create/find DM room)
     * accepts: {userId} or {to_user_id}
     * ------------------------------------------------------------------ */
    public function createConvo(Request $request)
    {
        $me = $request->user();
        if (!$me) return response()->json(['message' => 'Unauthorized'], 401);

        $data = $request->validate([
            'userId'     => ['nullable', 'integer'],
            'to_user_id' => ['nullable', 'integer'],
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

        $uid = (int) $me->id;

        return DB::transaction(function () use ($uid, $otherId) {

            // find existing DM room: isGroup=false and has exactly these two members
            $existingRoomId = ChatRoom::query()
                ->where('isGroup', false)
                ->whereHas('members', fn ($q) => $q->where('userId', $uid))
                ->whereHas('members', fn ($q) => $q->where('userId', $otherId))
                ->pluck('id')
                ->first();

            if ($existingRoomId) {
                $room = ChatRoom::find($existingRoomId);
            } else {
                $room = ChatRoom::create([
                    'name'       => null,
                    'isGroup'    => false,
                    'privateKey' => (string) Str::uuid(),
                ]);

                ChatRoomMember::create(['roomId' => (int) $room->id, 'userId' => $uid, 'createdAt' => now()]);
                ChatRoomMember::create(['roomId' => (int) $room->id, 'userId' => $otherId, 'createdAt' => now()]);
            }

            // bump updatedAt
            $room->touch();

            // shape response
            $membersGrouped = $this->membersForRoomIds([(int) $room->id]);
            $lastByRoom = $this->lastMessageByRoomIds([(int) $room->id]);

            return response()->json([
                'ok'   => true,
                'room' => $this->shapeRoom($room, $membersGrouped, $lastByRoom, $uid),
            ], 201);
        });
    }

    /* ------------------------------------------------------------------
     | GET /api/chat/messages/{room}
     * ------------------------------------------------------------------ */
    public function getMessages(Request $request, $room)
    {
        $me = $request->user();
        if (!$me) return response()->json([], 401);

        $uid = (int) $me->id;
        $roomId = (int) $room;

        $isMember = ChatRoomMember::query()
            ->where('roomId', $roomId)
            ->where('userId', $uid)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $messages = Message::query()
            ->where('roomId', $roomId)
            ->with('user:id,name,email')
            ->orderBy('createdAt', 'asc')
            ->get()
            ->map(fn (Message $m) => $this->shapeMessage($m))
            ->values();

        return response()->json($messages, 200);
    }

    /* ------------------------------------------------------------------
     | POST /api/chat/messages/{room}
     * body: {content} or {text}
     * ------------------------------------------------------------------ */
    public function postMessage(Request $request, $room)
    {
        $data = $request->validate([
            'content' => ['nullable', 'string'],
            'text'    => ['nullable', 'string'],
            'kind'    => ['nullable', 'string'],
        ]);

        $me = $request->user();
        if (!$me) return response()->json(['message' => 'Unauthorized'], 401);

        $uid = (int) $me->id;
        $roomId = (int) $room;

        $isMember = ChatRoomMember::query()
            ->where('roomId', $roomId)
            ->where('userId', $uid)
            ->exists();

        if (!$isMember) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $content = trim((string) ($data['content'] ?? $data['text'] ?? ''));
        if ($content === '') {
            return response()->json(['message' => 'INVALID_TEXT'], 400);
        }

        $message = Message::create([
            'roomId'  => $roomId,
            'userId'  => $uid,
            'content' => $content,
            'kind'    => $data['kind'] ?? 'text',
        ]);

        // bump room ordering
        $roomModel = ChatRoom::findOrFail($roomId);
        $roomModel->touch();

        // realtime: room channel
        try {
    $ev = new ChatMessageCreated($roomId, $message);
    $ev->dontBroadcastToCurrentUser(); // ✅ like typing
    broadcast($ev);
} catch (\Throwable $e) {
    Log::error('Broadcast failed (postMessage)', ['error' => $e->getMessage()]);
}

        // realtime: notify all OTHER members (private user channel)
        $memberIds = ChatRoomMember::query()
            ->where('roomId', $roomId)
            ->pluck('userId')
            ->map(fn ($x) => (int) $x)
            ->all();

        foreach ($memberIds as $toUserId) {
            if ($toUserId === $uid) continue;

            try {
                event(new ChatGlobalNotify($toUserId, [
    'type' => 'notify',
    'room_id' => $roomId,
    'roomId' => $roomId,
    'message' => $this->shapeMessage($message->fresh(['user:id,name,email'])),
  ]));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (notify)', [
                    'roomId' => $roomId,
                    'toUserId' => $toUserId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $payloadMsg = $this->shapeMessage($message->fresh(['user:id,name,email']));

        return response()->json([
            'ok'      => true,
            'type'    => 'message',
            'room_id' => (int) $roomId,
            'roomId'  => (int) $roomId,
            'message' => $payloadMsg,
        ], 201);
    }

    /* ------------------------------------------------------------------
     | POST /api/chat/friendship  (send friend request + ensure DM)
     * body: {to_user_id, content?}
     * ------------------------------------------------------------------ */
    public function sendFriendship(Request $request)
    {
        $data = $request->validate([
            'to_user_id' => ['required', 'integer'],
            'content'    => ['nullable', 'string'],
        ]);

        $from = $request->user();
        if (!$from) return response()->json(['message' => 'Unauthorized'], 401);

        $fromId = (int) $from->id;
        $toId = (int) $data['to_user_id'];

        if ($fromId === $toId) {
            return response()->json(['message' => 'نمی‌توانید خودتان را اضافه کنید.'], 422);
        }

        $to = User::find($toId);
        if (!$to) return response()->json(['message' => 'User not found'], 404);

        // existing friendship?
        $existing = Friendship::query()
            ->where(function ($q) use ($fromId, $toId) {
                $q->where('fromUserId', $fromId)->where('toUserId', $toId);
            })
            ->orWhere(function ($q) use ($fromId, $toId) {
                $q->where('fromUserId', $toId)->where('toUserId', $fromId);
            })
            ->first();

        return DB::transaction(function () use ($fromId, $toId, $existing, $data) {

            // ensure DM room between two users
            $existingRoomId = ChatRoom::query()
                ->where('isGroup', false)
                ->whereHas('members', fn ($q) => $q->where('userId', $fromId))
                ->whereHas('members', fn ($q) => $q->where('userId', $toId))
                ->pluck('id')
                ->first();

            if ($existingRoomId) {
                $room = ChatRoom::find($existingRoomId);
            } else {
                $room = ChatRoom::create([
                    'name'       => null,
                    'isGroup'    => false,
                    'privateKey' => (string) Str::uuid(),
                ]);

                ChatRoomMember::create(['roomId' => (int) $room->id, 'userId' => $fromId, 'createdAt' => now()]);
                ChatRoomMember::create(['roomId' => (int) $room->id, 'userId' => $toId, 'createdAt' => now()]);
            }

            $text = trim((string) ($data['content'] ?? '')) ?: 'سلام! من برایت درخواست دوستی فرستادم 🙌';

            $dm = Message::create([
                'roomId'  => (int) $room->id,
                'userId'  => $fromId,
                'content' => $text,
                'kind'    => 'friend_request',
            ]);

            $room->touch();

            // create friendship if not exists
            $friendship = $existing;
            $status = 200;

            if (!$existing) {
                $friendship = Friendship::create([
                    'fromUserId' => $fromId,
                    'toUserId'   => $toId,
                    'status'     => 'pending',
                ]);
                $status = 201;
            }

            // room event
            try {
                event(new ChatMessageCreated((int) $room->id, $dm));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (friend_request msg)', [
                    'msg_id' => $dm->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            // notify receiver + sender (optional but helpful for UI sync)
            foreach ([(int) $toId, (int) $fromId] as $notifyId) {
                try {
                    event(new ChatGlobalNotify($notifyId, [
                        'type'    => 'notify',
                        'room_id' => (int) $room->id,
                        'roomId'  => (int) $room->id,
                        'message' => $this->shapeMessage($dm->fresh(['user:id,name,email'])),
                    ]));
                } catch (\Throwable $e) {
                    Log::error('Broadcast failed (friend_request notify)', [
                        'room_id' => $room->id,
                        'to_user' => $notifyId,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $membersGrouped = $this->membersForRoomIds([(int) $room->id]);
            $lastByRoom = $this->lastMessageByRoomIds([(int) $room->id]);

            return response()->json([
                'message'    => $existing ? 'درخواست دوستی قبلاً ثبت شده است.' : 'Friend request sent.',
                'friendship' => $friendship,
                'room'       => $this->shapeRoom($room, $membersGrouped, $lastByRoom, $fromId),
                'dm_message' => $this->shapeMessage($dm->fresh(['user:id,name,email'])),
            ], $status);
        });
    }

    /* ------------------------------------------------------------------
     | POST /api/chat/friendship/respond  (optional legacy)
     * body: {friendship_id, action: accept|reject}
     * ------------------------------------------------------------------ */
    public function respondFriendship(Request $request)
    {
        $me = $request->user();
        if (!$me) return response()->json(['message' => 'Unauthorized'], 401);

        $data = $request->validate([
            'friendship_id' => ['required', 'integer'],
            'action'        => ['required', Rule::in(['accept', 'reject'])],
        ]);

        $fs = Friendship::findOrFail((int) $data['friendship_id']);

        if ((int) $fs->toUserId !== (int) $me->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $fs->status = $data['action'] === 'accept' ? 'accepted' : 'rejected';
        $fs->save();

        return response()->json([
            'message'        => 'Friend request updated.',
            'friendship_id'  => (int) $fs->id,
            'friendship_raw' => (string) $fs->status,
        ]);
    }

    /* ------------------------------------------------------------------
     | POST /api/chat/typing  (Laravel Reverb typing, exclude sender socket)
     * body: {room_id, isTyping}
     * ------------------------------------------------------------------ */
    public function typing(Request $request)
    {
        $validated = $request->validate([
            'room_id'  => 'required|integer',
            'isTyping' => 'required|boolean',
        ]);

        $roomId   = (int) $validated['room_id'];
        $userId   = (int) $request->user()->id;
        $isTyping = (bool) $validated['isTyping'];

        Log::info('[typing] hit', [
            'roomId'   => $roomId,
            'userId'   => $userId,
            'isTyping' => $isTyping,
            'socketId' => $request->header('X-Socket-Id'),
        ]);

        $event = new TypingIndicator($roomId, $userId, $isTyping);

        // exclude current socket (IMPORTANT)
        $event->dontBroadcastToCurrentUser();

        broadcast($event);

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------
     | GET /api/debug/broadcast/{roomId}  (optional)
     * ------------------------------------------------------------------ */
    public function debugBroadcast(Request $request, $roomId)
    {
        $me = $request->user();
        if (!$me) return response()->json(['message' => 'Unauthorized'], 401);

        $rid = (int) $roomId;

        $m = Message::create([
            'roomId'  => $rid,
            'userId'  => (int) $me->id,
            'content' => 'debug broadcast ' . now()->toDateTimeString(),
            'kind'    => 'text',
        ]);

        event(new ChatMessageCreated($rid, $m));

        return response()->json(['ok' => true, 'roomId' => $rid, 'messageId' => (int) $m->id]);
    }
}