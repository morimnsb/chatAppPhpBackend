<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

final class TypingIndicator implements ShouldBroadcastNow
{
    use SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $roomId,
        public int $userId,
        public bool $isTyping
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'chat.typing';
    }

    /**
     * @return array{
     *   type: 'typing',
     *   room_id: int,
     *   roomId: int,
     *   user_id: int,
     *   userId: int,
     *   isTyping: bool,
     *   at: int
     * }
     */
    public function broadcastWith(): array
{
    return [
        'type'     => 'typing',
        'room_id'  => (int) $this->roomId,
        'roomId'   => (int) $this->roomId,
        'user_id'  => (int) $this->userId,
        'userId'   => (int) $this->userId,
        'isTyping' => (bool) $this->isTyping,
        'at'       => ((int) now()->timestamp) * 1000, // ms (safe int)
    ];
}
}