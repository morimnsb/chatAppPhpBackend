<?php
// app/Listeners/HandleWsClientEvents.php

namespace App\Listeners;

use App\Events\ChatMessageCreated;
use App\Models\Message;

class HandleWsClientEvents
{
    public function handle($event): void
    {
        $payload = $event->message;   // همون data خام Reverb
        $eventName = $payload['event'] ?? null;
        $data      = $payload['data'] ?? [];
        $channel   = $payload['channel'] ?? null;

        if (! $eventName || ! $channel) {
            return;
        }

        if (str_starts_with($channel, 'chat.') === false) {
            return; // فعلاً فقط کانال‌های chat.*
        }

        $roomId = (int) str_replace('chat.', '', $channel);

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
        }
    }

    protected function handleClientChatMessage(int $roomId, array $data): void
    {
        $userId  = (int) ($data['user_id'] ?? 0);
        $content = trim((string) ($data['content'] ?? ''));

        if (! $userId || $content === '') {
            return;
        }

        $message = Message::create([
            'chat_room_id' => $roomId,
            'user_id'      => $userId,
            'content'      => $content,
        ]);

        broadcast(new ChatMessageCreated($roomId, $message));
    }

    protected function handleClientTyping(int $roomId, array $data): void
    {
        $userId = (int) ($data['user_id'] ?? 0);
        if (! $userId) return;

        broadcast(new \App\Events\TypingUpdated($roomId, $userId));
    }

    protected function handleClientReadReceipt(int $roomId, array $data): void
    {
        $messageId = (int) ($data['message_id'] ?? 0);
        $readerId  = (int) ($data['reader_id'] ?? 0);

        if (! $messageId || ! $readerId) return;

        // اینجا می‌تونی پیام رو به عنوان خوانده شده در DB علامت بزنی

        broadcast(new \App\Events\MessageRead($roomId, $messageId, $readerId));
    }
}
