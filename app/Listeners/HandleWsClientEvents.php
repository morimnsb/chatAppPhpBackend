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
        // بعضی جاها ممکنه message آبجکت/استرینگ باشه، پس امن می‌گیریم
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

        if (!str_starts_with($channel, 'chat.')) {
            return; // فعلاً فقط chat.*
        }

        $roomId = (int) str_replace('chat.', '', $channel);
        if ($roomId <= 0) {
            Log::warning('WS client event: invalid roomId parsed from channel', [
                'channel' => $channel,
                'roomId' => $roomId,
            ]);
            return;
        }

        Log::info('WS client event received', [
            'event' => $eventName,
            'room_id' => $roomId,
            'data' => $data,
        ]);

        switch ($eventName) {
            case 'ClientChatMessage':
                $this->handleClientChatMessage($roomId, $data);
                break;

            case 'ClientTyping':
                $this->handleClientTyping($roomId, $data);
                break;

            case 'ClientReadReceipt':
                $this->handleClientReadReceipt($roomId, $data);
                break;

            default:
                Log::debug('WS client event: unhandled event', [
                    'event' => $eventName,
                    'room_id' => $roomId,
                ]);
                break;
        }
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

            // ✅ broadcast سروری به کل کلاینت‌ها
            broadcast(new ChatMessageCreated($roomId, $message));
            // اگر نخواهی خود فرستنده هم دوباره دریافت کند:
            // broadcast(new ChatMessageCreated($roomId, $message))->toOthers();

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

    protected function handleClientTyping(int $roomId, array $data): void
    {
        $userId = (int) ($data['user_id'] ?? 0);
        if (!$userId) return;

        // اگر رویداد تایپینگ را واقعاً داری:
        // broadcast(new \App\Events\TypingUpdated($roomId, $userId));
        Log::debug('ClientTyping received', [
            'room_id' => $roomId,
            'user_id' => $userId,
        ]);
    }

    protected function handleClientReadReceipt(int $roomId, array $data): void
    {
        $messageId = (int) ($data['message_id'] ?? 0);
        $readerId  = (int) ($data['reader_id'] ?? 0);

        if (!$messageId || !$readerId) return;

        // TODO: mark as read in DB
        // broadcast(new \App\Events\MessageRead($roomId, $messageId, $readerId));

        Log::debug('ClientReadReceipt received', [
            'room_id' => $roomId,
            'message_id' => $messageId,
            'reader_id' => $readerId,
        ]);
    }
}
