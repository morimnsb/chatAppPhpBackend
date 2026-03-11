<?php declare(strict_types=1);

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class ChatGlobalNotify implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** @param array<string,mixed> $payload */
    public function __construct(
        public int $toUserId,
        public array $payload
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->toUserId);
    }

    public function broadcastAs(): string
    {
        return 'user.notify';
    }

    /** @return array<string,mixed> */
    public function broadcastWith(): array
    {
        // ✅ guarantee ints (no mixed->int cast warnings)
        $payload = $this->payload;

        $payload['to_user_id'] = $this->toUserId;
        $payload['toUserId'] = $this->toUserId;

        return $payload;
    }
}