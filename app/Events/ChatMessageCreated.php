<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

class ChatMessageCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels, InteractsWithSockets;

    public int $roomId;
    public Message $message;

    /**
     * @param  int              $roomId   chat_rooms.id
     * @param  \App\Models\Message  $message  مدل مسیج تازه‌ساخته‌شده
     */
    public function __construct(int $roomId, Message $message)
    {
        $this->roomId  = $roomId;
        $this->message = $message;
    }

    /**
     * کانالی که Reverb رویش broadcast می‌کند
     * فرانت هم subscribe کرده روی: chat.{roomId}
     */
    public function broadcastOn(): Channel
    {
        return new Channel('chat.' . $this->roomId);
        // اگر بعداً PrivateChannel بخوای:
        // return new PrivateChannel('chat.' . $this->roomId);
    }

    /**
     * اسم event سمت کلاینت
     * یعنی در فرانت باید گوش بدی روی: 'ChatMessageCreated'
     */
    public function broadcastAs(): string
    {
        return 'ChatMessageCreated';
    }

    /**
     * دیتا‌یی که واقعاً برای فرانت فرستاده می‌شود
     */
    public function broadcastWith(): array
    {
        // مطمئن می‌شیم user لود شده:
        $message = $this->message->loadMissing('user:id,name,email');

        return [
            'type'    => 'message',
            'room_id' => $this->roomId,
            'message' => [
                'id'         => $message->id,
                'room_id'    => $message->chat_room_id ?? $this->roomId,
                'sender_id'  => $message->user_id,
                'sender'     => $message->user ? [
                    'id'    => $message->user->id,
                    'name'  => $message->user->name,
                    'email' => $message->user->email,
                ] : null,
                'content'    => $message->content,
                'kind'       => $message->kind, // text / friend_request / system
                'created_at' => optional($message->created_at)->toIso8601String(),
            ],
        ];
    }
}
