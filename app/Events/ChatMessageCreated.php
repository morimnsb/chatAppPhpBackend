<?php
// chatAppPhpBackend/app/Events/ChatMessageCreated.php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $roomId,
        public Message $message
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        // ✅ Standard channel name for room events
        return new PrivateChannel('chat.' . $this->roomId);
        // اگر می‌خوای دقیقاً private-chat.{id} باشه:
        // return new PrivateChannel('private-chat.' . $this->roomId);
    }

    public function broadcastAs(): string
    {
        // ✅ Canonical event name across all backends
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        $m = $this->message->loadMissing('user:id,name,email');

        $msg = [
            'id'           => (int) $m->id,
            'chat_room_id' => (int) $m->chat_room_id,
            'room_id'      => (int) $m->chat_room_id,
            'roomId'       => (int) $m->chat_room_id,

            'user_id'      => (int) $m->user_id,
            'userId'       => (int) $m->user_id,
            'sender_id'    => (int) $m->user_id,
            'senderId'     => (int) $m->user_id,

            'content'      => $m->content,
            'text'         => $m->content,
            'kind'         => $m->kind ?? 'text',

            'created_at'   => optional($m->created_at)->toIso8601String(),
            'updated_at'   => optional($m->updated_at)->toIso8601String(),

            'user'         => $m->user ? [
                'id'    => (int) $m->user->id,
                'name'  => (string) $m->user->name,
                'email' => (string) $m->user->email,
            ] : null,
        ];

        return [
            'type'    => 'message',
            'room_id' => (int) $this->roomId,
            'roomId'  => (int) $this->roomId,
            'message' => $msg,
        ];
    }
}