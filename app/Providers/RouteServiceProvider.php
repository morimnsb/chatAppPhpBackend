// app/Providers/RouteServiceProvider.php (method: configureRateLimiting)
<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

RateLimiter::for('chat-actions', function (Request $request) {
    $key = 'chat:'.$request->user()?->id ?: $request->ip();
    return [
        Limit::perMinute(10)->by($key),   // MAX_MESSAGES_PER_WINDOW = 10
    ];
});
