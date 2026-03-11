<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): Channel
    {
        $roomId = (int) $this->message->room_id;

        return new PrivateChannel('private-chat.' . $roomId);
    }

    public function broadcastAs(): string
    {
        return 'chat.message.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $m = $this->message->loadMissing('user:id,name,email');

        $createdAt = $m->created_at;
        $createdIso = $createdAt instanceof \DateTimeInterface
            ? $createdAt->format(\DateTimeInterface::ATOM)
            : null;

        return [
            'id'         => (int) $m->id,
            'room_id'    => (int) $m->room_id,
            'user_id'    => (int) $m->user_id,
            'content'    => $m->content ?? null,
            'kind'       => $m->kind ?? null,
            'created_at' => $createdIso,
        ];
    }
}