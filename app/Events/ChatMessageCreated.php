<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow; // ✅ مهم
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $roomId;
    public Message $message;

    public function __construct(int $roomId, Message $message)
    {
        $this->roomId = $roomId;
        $this->message = $message;
    }

    public function broadcastOn()
    {
        // ✅ چون فرانت: echo.private(`chat.${roomId}`)
        return new PrivateChannel('chat.' . $this->roomId);
    }

    public function broadcastAs(): string
    {
        // ✅ فرانت: listen('ChatMessageCreated') و listen('.ChatMessageCreated')
        return 'ChatMessageCreated';
    }

    public function broadcastWith(): array
    {
        $m = $this->message->loadMissing('user:id,name,email,photo');

        return [
            'type' => 'message',
            'room_id' => (int) $this->roomId,
            'message' => [
                'id'           => (int) $m->id,
                'chat_room_id' => (int) $m->chat_room_id,
                'user_id'      => (int) $m->user_id,
                'content'      => $m->content ?? null,
                'kind'         => $m->kind ?? null,
                'created_at'   => optional($m->created_at)->toIso8601String(),
                'updated_at'   => optional($m->updated_at)->toIso8601String(),
                'user'         => $m->user ? [
                    'id'    => (int) $m->user->id,
                    'name'  => (string) $m->user->name,
                    'email' => (string) $m->user->email,
                    'photo' => $m->user->photo ?? null,
                ] : null,
            ],
        ];
    }
}
