// app/Events/MessagePushed.php
<?php


use Illuminate\Broadcasting\Channel;      // یا PresenceChannel/PrivateChannel
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;
namespace App\Events;

class MessagePushed implements ShouldBroadcast
{
    use SerializesModels;

    public int $roomId;
    public array $payload;

    public function __construct(int $roomId, array $payload)
    {
        $this->roomId = $roomId;
        $this->payload = $payload;
    }

    public function broadcastOn()
    {
        // فعلاً public channel. بعداً اگر خواستی private کن.
        return new Channel('room.'.$this->roomId);
    }

    public function broadcastAs()
    {
        return 'MessagePushed';
    }
}
