<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        // فقط user را لازم داریم
        $this->message = $message->load('user');
    }

    public function broadcastConnection(): string
    {
        // باید با BROADCAST_CONNECTION = reverb مچ باشد
        return 'reverb';
    }

    public function broadcastOn(): array
    {
        // اگر ستون‌ات chat_room_id است، از آن استفاده می‌کنیم
        $roomId = $this->message->chat_room_id ?? $this->message->room_id;

        return [
            new PresenceChannel('room.' . $roomId),
        ];
    }

    public function broadcastAs(): string
    {
        // front گوش می‌دهد به ".chat.message"
        return 'chat.message';
    }

    public function broadcastWith(): array
    {
        $m = $this->message;
        $sender = $m->user;
        $roomId = $m->chat_room_id ?? $m->room_id;

        return [
            'type'    => 'message',
            'message' => [
                'id'                => $m->id,
                'room_id'           => $roomId,
                'content'           => $m->content,
                'sender_id'         => $m->user_id,
                'sender_first_name' => $sender?->name ?? $sender?->email ?? null,
                'photo'             => $sender?->photo_url ?? null,
                'created_at'        => optional($m->created_at)->toIso8601String(),
                'timestamp'         => optional($m->created_at)->toIso8601String(),
                'read_receipt'      => (bool) ($m->read_receipt ?? false),
            ],
        ];
    }
}
