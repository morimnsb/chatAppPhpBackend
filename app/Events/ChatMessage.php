<?php
// app/Events/ChatMessage.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class ChatMessage implements ShouldBroadcast
{
    use SerializesModels;

    public function __construct(
        public int $roomId,
        public array $payload // { messageId, text, senderId, ... }
    ) {}

    public function broadcastOn(): array
    {
        return [ new Channel("room.{$this->roomId}") ];
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
