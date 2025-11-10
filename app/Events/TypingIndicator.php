<?php
// app/Events/TypingIndicator.php

// app/Events/TypingIndicator.php
namespace App\Events;

use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class TypingIndicator implements ShouldBroadcast
{
    public function __construct(public int $roomId, public int $userId) {}

    public function broadcastOn(): array
    {
        return [ new PresenceChannel("presence.room.{$this->roomId}") ];
    }

    public function broadcastAs(): string
    {
        return 'typing.indicator';
    }

    public function broadcastWith(): array
    {
        return ['userId' => $this->userId];
    }
}
