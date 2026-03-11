<?php declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ChatRoomService
{
    public function __construct(
        private readonly ChatFormatterService $formatter,
    ) {
    }

    private function toInt(mixed $v, int $default = 0): int
    {
        if (is_int($v)) return $v;
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_string($v) || is_float($v)) return is_numeric((string) $v) ? (int) $v : $default;
        return $default;
    }

    /**
     * @param array<int,int> $roomIds
     * @return Collection<int, EloquentCollection<int, ChatRoomMember>>
     */
    public function membersForRoomIds(array $roomIds): Collection
    {
        return ChatRoomMember::query()
            ->whereIn('room_id', $roomIds)
            ->with(['user:id,name,email'])
            ->get()
            ->groupBy('room_id');
    }

    /**
     * @param array<int,int> $roomIds
     * @return array<int, Message|null>
     */
    public function lastMessageByRoomIds(array $roomIds): array
    {
        /** @var Collection<int, EloquentCollection<int, Message>> $grouped */
        $grouped = Message::query()
            ->whereIn('room_id', $roomIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('room_id');

        $out = [];
        foreach ($grouped as $rid => $msgs) {
            $out[$this->toInt($rid, 0)] = $msgs->first();
        }

        return $out;
    }

    /**
     * @return Collection<int, array<string,mixed>>
     */
    public function listRoomsForUser(int $userId): Collection
    {
        /** @var EloquentCollection<int, ChatRoom> $rooms */
        $rooms = ChatRoom::query()
            ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
            ->orderByDesc('updated_at')
            ->get();

        /** @var array<int,int> $roomIds */
        $roomIds = $rooms->pluck('id')
            ->map(fn (mixed $x): int => $this->toInt($x, 0))
            ->all();

        $membersGrouped = $this->membersForRoomIds($roomIds);
        $lastByRoom = $this->lastMessageByRoomIds($roomIds);

        return $rooms
            ->map(fn (ChatRoom $r): array => $this->formatter->shapeRoom($r, $membersGrouped, $lastByRoom, $userId))
            ->values();
    }

    public function findOrCreateDmRoom(int $userId, int $otherId): ChatRoom
    {
        /** @var ChatRoom $room */
        $room = DB::transaction(function () use ($userId, $otherId): ChatRoom {
            $val = ChatRoom::query()
                ->where('is_group', false)
                ->whereHas('members', fn ($q) => $q->where('user_id', $userId))
                ->whereHas('members', fn ($q) => $q->where('user_id', $otherId))
                ->value('id');

            $existingRoomId = $this->toInt($val, 0);
            $room = $existingRoomId > 0 ? ChatRoom::find($existingRoomId) : null;

            if ($room === null) {
                $room = ChatRoom::create([
                    'name'        => null,
                    'is_group'    => false,
                    'private_key' => (string) Str::uuid(),
                ]);

                ChatRoomMember::create([
                    'room_id' => (int) $room->id,
                    'user_id' => $userId,
                ]);

                ChatRoomMember::create([
                    'room_id' => (int) $room->id,
                    'user_id' => $otherId,
                ]);
            }

            $room->touch();

            return $room;
        });

        return $room;
    }

    /**
     * @return array<string,mixed>
     */
    public function createConversation(int $userId, int $otherId): array
    {
        $room = $this->findOrCreateDmRoom($userId, $otherId);

        $membersGrouped = $this->membersForRoomIds([(int) $room->id]);
        $lastByRoom = $this->lastMessageByRoomIds([(int) $room->id]);

        return $this->formatter->shapeRoom($room, $membersGrouped, $lastByRoom, $userId);
    }

    public function assertUserExists(int $otherId): ?User
    {
        return User::find($otherId);
    }
}