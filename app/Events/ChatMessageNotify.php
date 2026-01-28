<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageNotify implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $receiverId;
    public Message $message;

    public function __construct(int $receiverId, Message $message)
    {
        $this->receiverId = $receiverId;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new PrivateChannel('user.' . $this->receiverId);
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageNotify';
    }

    public function broadcastWith(): array
    {
        $m = $this->message->loadMissing('user:id,name,photo');

        return [
            'type' => 'message_notify',
            'room_id' => (int) $m->chat_room_id,
            'message_id' => (int) $m->id,
            'from' => [
                'id' => (int) $m->user->id,
                'name' => (string) $m->user->name,
                'photo' => $m->user->photo ?? null,
            ],
            'preview' => mb_strimwidth((string) ($m->content ?? ''), 0, 60, '…'),
            'created_at' => optional($m->created_at)->toIso8601String(),
        ];
    }
}
