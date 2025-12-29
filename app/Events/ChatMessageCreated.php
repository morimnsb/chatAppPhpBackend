<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public int $roomId;
    public Message $message;

    public function __construct(int $roomId, Message $message)
    {
        $this->roomId  = $roomId;
        $this->message = $message;
    }

    /**
     * ✅ IMPORTANT:
     * Front uses: echo.private(`chat.${roomId}`)
     * so the real wire channel is: private-chat.{roomId}
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('chat.' . $this->roomId);
    }

    /**
     * Front listens to: '.ChatMessageCreated'
     */
    public function broadcastAs(): string
    {
        return 'ChatMessageCreated';
    }

    /**
     * Data sent to frontend
     */
    public function broadcastWith(): array
    {
        $message = $this->message->loadMissing('user:id,name,email');

        return [
            'type'    => 'message',
            'room_id' => $this->roomId,
            'message' => [
                'id'         => $message->id,
                'room_id'    => (int) ($message->chat_room_id ?? $this->roomId),
                'sender_id'  => (int) $message->user_id,
                'sender'     => $message->user ? [
                    'id'    => (int) $message->user->id,
                    'name'  => (string) $message->user->name,
                    'email' => (string) $message->user->email,
                ] : null,
                'content'    => (string) $message->content,
                'kind'       => $message->kind ?? null,
                'created_at' => optional($message->created_at)->toIso8601String(),
            ],
        ];
    }
}
