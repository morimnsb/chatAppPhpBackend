<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets; // ✅ add

class TypingIndicator implements ShouldBroadcastNow
{
    use SerializesModels, InteractsWithSockets; // ✅ add

    public int $roomId;
    public int $userId;
    public bool $isTyping;

    public function __construct(int $roomId, int $userId, bool $isTyping)
    {
        $this->roomId = $roomId;
        $this->userId = $userId;
        $this->isTyping = $isTyping;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->roomId);
    }

    public function broadcastAs()
    {
        return 'typing_indicator';
    }

    public function broadcastWith()
    {
        return [
            'type' => 'typing_indicator',
            'room_id' => $this->roomId,
            'roomId' => $this->roomId,
            'user_id' => $this->userId,
            'userId' => $this->userId,
            'isTyping' => $this->isTyping,
            'at' => now()->timestamp * 1000,
        ];
    }
}
