<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;

class ReverbTap
{
    public function handle(MessageReceived $event): void
    {
        $raw = $event->message;

        // message ممکنه string باشه
        $payload = is_string($raw) ? json_decode($raw, true) : $raw;

        if (!is_array($payload)) return;

        $name = $payload['event'] ?? null;
        $channel = $payload['channel'] ?? null;

        // ping/subscribe رو حذف کن که اسپم نشه
        if (!$name || str_starts_with($name, 'pusher:')) return;

        // فقط وقتی channel داریم (یعنی پیام channel-based)
        if (!$channel) return;

        Log::info('Reverb message (non-pusher)', [
            'event' => $name,
            'channel' => $channel,
            'data' => $payload['data'] ?? null,
        ]);
    }
}
