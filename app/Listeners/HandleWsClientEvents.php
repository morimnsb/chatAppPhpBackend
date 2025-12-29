<?php
// app/Listeners/HandleWsClientEvents.php

namespace App\Listeners;

use App\Events\ChatMessageCreated;
use App\Models\Message;
use Illuminate\Support\Facades\Log;

class HandleWsClientEvents
{
    public function handle($event): void
    {
        $payload = $event->message ?? null;

        if (!is_array($payload)) {
            Log::debug('WS client event: payload is not array', [
                'type' => gettype($payload),
            ]);
            return;
        }

        $eventName = $payload['event'] ?? null;
        $data      = $payload['data'] ?? [];
        $channel   = $payload['channel'] ?? null;

        if (!$eventName || !$channel) {
            Log::debug('WS client event: missing eventName/channel', [
                'event' => $eventName,
                'channel' => $channel,
                'payload' => $payload,
            ]);
            return;
        }

        // ✅ Accept both: chat.1  OR  private-chat.1
        $roomId = $this->parseRoomId($channel);
        if ($roomId <= 0) {
            Log::warning('WS client event: invalid roomId from channel', [
                'channel' => $channel,
            ]);
            return;
        }

        Log::info('WS client event received', [
            'event' => $eventName,
            'room_id' => $roomId,
            'channel' => $channel,
            'data' => $data,
        ]);

        switch ($eventName) {
            case 'ClientChatMessage':
            case 'client-chat-message':
                $this->handleClientChatMessage($roomId, $data);
                break;

            case 'ClientTyping':
            case 'client-typing':
                // تو گفتی ذخیره نکنیم؛ فقط log کافیه
                Log::debug('ClientTyping received', [
                    'room_id' => $roomId,
                    'data' => $data,
                ]);
                break;

            default:
                Log::debug('WS client event: unhandled event', [
                    'event' => $eventName,
                    'room_id' => $roomId,
                ]);
                break;
        }
    }

    private function parseRoomId(string $channel): int
    {
        // "private-chat.1" → 1
        if (str_starts_with($channel, 'private-chat.')) {
            return (int) substr($channel, strlen('private-chat.'));
        }

        // "chat.1" → 1
        if (str_starts_with($channel, 'chat.')) {
            return (int) substr($channel, strlen('chat.'));
        }

        // "private-private-chat.1" (اگر یه جایی اشتباه ساخته شده) → هم هندل کنیم
        if (str_starts_with($channel, 'private-private-chat.')) {
            return (int) substr($channel, strlen('private-private-chat.'));
        }

        return 0;
    }

    protected function handleClientChatMessage(int $roomId, array $data): void
    {
        $userId  = (int) ($data['user_id'] ?? 0);
        $content = trim((string) ($data['content'] ?? ''));

        if (!$userId || $content === '') {
            Log::warning('ClientChatMessage invalid data', [
                'room_id' => $roomId,
                'data' => $data,
            ]);
            return;
        }

        try {
            $message = Message::create([
                'chat_room_id' => $roomId,
                'user_id'      => $userId,
                'content'      => $content,
            ]);

            Log::info('WS chat message saved', [
                'room_id' => $roomId,
                'message_id' => $message->id,
                'user_id' => $userId,
            ]);

            // ✅ broadcast سروری به همه‌ی اعضای private-chat.{roomId}
            broadcast(new ChatMessageCreated($roomId, $message));

            Log::info('WS chat message broadcasted', [
                'room_id' => $roomId,
                'message_id' => $message->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('WS chat message failed', [
                'room_id' => $roomId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
