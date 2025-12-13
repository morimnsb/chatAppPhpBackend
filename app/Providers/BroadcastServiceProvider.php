<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;

class BroadcastServiceProvider extends ServiceProvider
{
  

public function boot(): void
{
    Broadcast::routes([
        'middleware' => ['auth:sanctum'], // 👈 همون گاردی که برای API استفاده می‌کنی
    ]);

    require base_path('routes/channels.php');
}


}
