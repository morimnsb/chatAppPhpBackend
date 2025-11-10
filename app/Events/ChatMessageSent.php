<?php
// app/Events/ChatMessageSent.php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public int $roomId;
    public array $payload;

    public function __construct(int $roomId, array $payload)
    {
        $this->roomId = $roomId;
        $this->payload = $payload;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('room.'.$this->roomId); // کانال خصوصی/عمومی؟
        // اگر private: return new PrivateChannel('room.'.$this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'chat.message';
    }
}
