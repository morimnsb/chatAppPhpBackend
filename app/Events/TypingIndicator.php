<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class TypingIndicator implements ShouldBroadcastNow
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
        // یا:
        // return new PrivateChannel('private-chat.' . $this->roomId);
    }

    public function broadcastAs(): string
    {
        // ✅ Canonical event name across all backends
        return 'chat.typing';
    }

    public function broadcastWith(): array
    {
        return [
            'type'     => 'typing',
            'room_id'  => (int) $this->roomId,
            'roomId'   => (int) $this->roomId,
            'user_id'  => (int) $this->userId,
            'userId'   => (int) $this->userId,
            'isTyping' => (bool) $this->isTyping,
            'at'       => now()->timestamp * 1000, // ms
        ];
    }
}