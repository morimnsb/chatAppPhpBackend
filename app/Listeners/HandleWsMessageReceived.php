<?php

namespace App\Listeners;

use App\Events\ChatMessageCreated;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
    use App\Models\ChatRoom;
class HandleWsMessageReceived
{
    public function handle(array $payload): void
    {
        $event = data_get($payload, 'event');

        switch ($event) {
            case 'ClientChatMessage':
                $this->handleClientChatMessage($payload);
                break;

            case 'ClientTyping':
                $this->handleClientTyping($payload);
                break;

            default:
                Log::debug('HandleWsMessageReceived: unknown event', [
                    'event'   => $event,
                    'payload' => $payload,
                ]);
        }
    }


protected function handleClientChatMessage(array $payload): void
{
    $roomId  = (int) data_get($payload, 'data.room_id');
    $userId  = (int) data_get($payload, 'data.user_id');
    $content = trim((string) data_get($payload, 'data.content', ''));

    Log::info('WS ClientChatMessage received', [
        'room_id' => $roomId,
        'user_id' => $userId,
        'content' => $content,
    ]);

    if (!$roomId || !$userId || $content === '') {
        Log::warning('WS chat: invalid payload', ['payload' => $payload]);
        return;
    }

    try {
        $message = Message::create([
            'chat_room_id' => $roomId,
            'user_id'      => $userId,
            'content'      => $content,
        ]);

        Log::info('WS chat: message created', [
            'id'           => $message->id,
            'chat_room_id' => $message->chat_room_id,
        ]);

        ChatRoom::whereKey($roomId)->update([
            'last_message_at' => now(),
        ]);

        // ⬇️ این مهمه: همون ایونتی که HTTP استفاده می‌کند
        event(new ChatMessageCreated($message));
    } catch (\Throwable $e) {
        Log::error('WS chat: failed to create message', [
            'room_id' => $roomId,
            'user_id' => $userId,
            'error'   => $e->getMessage(),
            'trace'   => $e->getTraceAsString(),
        ]);
    }
}




    protected function handleClientTyping(array $payload): void
    {
        $roomId = (int) data_get($payload, 'data.room_id');
        $userId = (int) data_get($payload, 'data.user_id');

        Log::info('HandleWsMessageReceived: ClientTyping', [
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);

        // اگر بعداً رو تایپینگ هم بخوای broadcast کنی، اینجا انجام می‌دیم
        // فعلاً فقط لاگ.
    }
}
