<?php declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\ChatGlobalNotify;
use App\Events\ChatMessageCreated;
use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ChatMessageService
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

    public function userIsMember(int $roomId, int $userId): bool
    {
        return ChatRoomMember::query()
            ->where('room_id', $roomId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * @return Collection<int, array<string,mixed>>
     */
    public function listMessages(int $roomId): Collection
    {
        return Message::query()
            ->where('room_id', $roomId)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Message $m): array => $this->formatter->shapeMessage($m))
            ->values();
    }

    public function createMessage(int $roomId, int $userId, string $content, string $kind = 'text'): Message
    {
        $message = Message::create([
            'room_id' => $roomId,
            'user_id' => $userId,
            'content' => $content,
            'kind'    => $kind,
        ]);

        $roomModel = ChatRoom::findOrFail($roomId);
        $roomModel->touch();

        try {
            $ev = new ChatMessageCreated($message);
            $ev->dontBroadcastToCurrentUser();
            broadcast($ev);
        } catch (\Throwable $e) {
            Log::error('Broadcast failed (postMessage)', [
                'error' => $e->getMessage(),
            ]);
        }

        /** @var array<int,int> $memberIds */
        $memberIds = ChatRoomMember::query()
            ->where('room_id', $roomId)
            ->pluck('user_id')
            ->map(fn (mixed $x): int => $this->toInt($x, 0))
            ->all();

        foreach ($memberIds as $toUserId) {
            if ($toUserId === $userId) continue;

            try {
                $fresh = $message->fresh(['user:id,name,email']);
                if (!$fresh instanceof Message) continue;

                event(new ChatGlobalNotify($toUserId, [
                    'type'    => 'notify',
                    'room_id' => $roomId,
                    'roomId'  => $roomId,
                    'message' => $this->formatter->shapeMessage($fresh),
                ]));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (notify)', [
                    'roomId'   => $roomId,
                    'toUserId' => $toUserId,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        return $message;
    }
}