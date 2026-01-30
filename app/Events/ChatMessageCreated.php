<?php
// chatAppPhpBackend\app\Events\ChatMessageCreated.php
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
        return new PrivateChannel('chat.' . $this->roomId);
    }

    public function broadcastAs(): string
    {
        return 'ChatMessageCreated';
    }

    public function broadcastWith(): array
    {
        $m = $this->message->loadMissing('user:id,name,email');

        return [
            'type' => 'message',
            'room_id' => (int) $this->roomId,
            'message' => [
                'id' => (int) $m->id,
                'chat_room_id' => (int) $m->chat_room_id,
                'user_id' => (int) $m->user_id,
                'content' => $m->content,
                'kind' => $m->kind,
                'created_at' => optional($m->created_at)->toIso8601String(),
                'updated_at' => optional($m->updated_at)->toIso8601String(),
                'user' => [
                    'id' => (int) $m->user->id,
                    'name' => $m->user->name,
                    'email' => $m->user->email,
                ],
            ],
        ];
    }
}
