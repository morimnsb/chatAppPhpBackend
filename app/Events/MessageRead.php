<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class MessageRead implements ShouldBroadcast
{
    public function __construct(public string $roomId, public int|string $messageId) {}

    public function broadcastOn() { return new Channel("room.{$this->roomId}"); }

    public function broadcastAs() { return 'message_received'; }

    public function broadcastWith() {
        return ['type' => 'message_received', 'message' => $this->messageId];
    }
}
