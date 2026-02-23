<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;

class ChatGlobalNotify implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public function __construct(
        public int $userId,
        public array $payload
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        // ✅ Standard channel for per-user events
        return new PrivateChannel('user.' . $this->userId);
        // اگر می‌خوای دقیقاً private-user.{id} باشه:
        // return new PrivateChannel('private-user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        // ✅ Canonical event name across all backends
        return 'user.notify';
    }

    public function broadcastWith(): array
    {
        // ✅ Ensure a predictable envelope
        $p = $this->payload;

        if (!is_array($p)) $p = [];

        // force type
        if (!isset($p['type'])) $p['type'] = 'notify';

        // keep compat aliases if present
        if (isset($p['room_id']) && !isset($p['roomId'])) $p['roomId'] = (int) $p['room_id'];
        if (isset($p['roomId']) && !isset($p['room_id'])) $p['room_id'] = (int) $p['roomId'];

        return $p;
    }
}