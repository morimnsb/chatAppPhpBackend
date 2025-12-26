<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Broadcast::routes([
            'middleware' => ['auth:sanctum'],
            // اگر CORS داری و API روی دامنه جداست، ممکنه لازم بشه:
            // 'prefix' => 'api',
        ]);

        require base_path('routes/channels.php');
    }
}
