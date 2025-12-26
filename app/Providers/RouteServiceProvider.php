<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class RouteServiceProvider extends ServiceProvider
{
    protected function configureRateLimiting(): void
    {
        // ✅ اگر cache هنوز bind نشده بود، artisan رو نکُش
        if (!app()->bound('cache.store')) {
            return;
        }

        RateLimiter::for('chat-actions', function (Request $request) {
            $key = 'chat:' . ($request->user()?->id ?? $request->ip());

            return [
                Limit::perMinute(10)->by($key),
            ];
        });
    }
}
