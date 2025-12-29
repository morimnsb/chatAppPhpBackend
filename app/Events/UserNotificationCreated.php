<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public int $toUserId;
    public array $payload;

    public function __construct(int $toUserId, array $payload)
    {
        $this->toUserId = $toUserId;
        $this->payload  = $payload;
    }

    public function broadcastOn(): PrivateChannel
    {
        // Echo: echo.private('user.2') => wire: private-user.2
        return new PrivateChannel('user.' . $this->toUserId);
    }

    public function broadcastAs(): string
    {
        return 'NotificationCreated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
