<?php

namespace App\Listeners;

use App\Events\ChatMessageCreated;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;

class HandleWsMessageReceived
{
    public function handle(MessageReceived $event): void
    {
        // $event->message معمولاً JSON string هست
        $payload = json_decode($event->message ?? '', true);

        if (!is_array($payload)) {
            Log::warning('WS: invalid JSON payload', ['raw' => $event->message]);
            return;
        }

        $eventName = data_get($payload, 'event');
        $data      = data_get($payload, 'data', []);
        $channel   = data_get($payload, 'channel'); // مثل chat.1

        Log::debug('WS: MessageReceived', [
            'event'   => $eventName,
            'channel' => $channel,
            'data'    => $data,
        ]);

        switch ($eventName) {
            case 'ClientChatMessage':
                $this->handleClientChatMessage($data, $channel);
                break;

            case 'ClientTyping':
                $this->handleClientTyping($data, $channel);
                break;

            default:
                // ping/subscribe/unsubscribe هم اینجا میاد
                Log::debug('WS: ignored event', ['event' => $eventName]);
        }
    }

    protected function handleClientChatMessage(array $data, ?string $channel): void
    {
        $roomId  = (int) data_get($data, 'room_id');
        $userId  = (int) data_get($data, 'user_id');
        $content = trim((string) data_get($data, 'content', ''));

        if ($roomId <= 0 || $userId <= 0 || $content === '') {
            Log::warning('WS chat: invalid data', ['data' => $data, 'channel' => $channel]);
            return;
        }

        $message = Message::create([
            'chat_room_id' => $roomId,
            'user_id'      => $userId,
            'content'      => $content,
        ]);

        ChatRoom::whereKey($roomId)->update([
            'last_message_at' => now(),
        ]);

        // ✅ اینجا باید (roomId, message) بدی
        broadcast(new ChatMessageCreated($roomId, $message));

        Log::info('WS chat: saved & broadcasted', [
            'message_id' => $message->id,
            'room_id'    => $roomId,
        ]);
    }

    protected function handleClientTyping(array $data, ?string $channel): void
    {
        Log::info('WS typing', [
            'room_id'  => (int) data_get($data, 'room_id'),
            'user_id'  => (int) data_get($data, 'user_id'),
            'channel'  => $channel,
        ]);
    }
}
