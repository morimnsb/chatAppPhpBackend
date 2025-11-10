<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
  

public function boot(): void
{
    Broadcast::routes([
        'prefix' => 'broadcasting',
        'middleware' => ['api', 'auth:sanctum'], // CORS سراسری است و قبل از این اجرا می‌شود
    ]);

    require base_path('routes/channels.php');
}

}
