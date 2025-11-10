<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class UserTyping implements ShouldBroadcast
{
    public function __construct(public string $roomId, public int|string $userId) {}

    public function broadcastOn() { return new Channel("room.{$this->roomId}"); }

    public function broadcastAs() { return 'typing_indicator'; }

    public function broadcastWith() {
        return ['type' => 'typing_indicator', 'user_id' => $this->userId];
    }
}
