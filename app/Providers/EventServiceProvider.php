<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Laravel\Reverb\Events\MessageReceived;
use App\Listeners\HandleWsMessageReceived;
use App\Listeners\ReverbTap;


class EventServiceProvider extends ServiceProvider
{
   



protected $listen = [
    \App\Events\WsClientEventReceived::class => [
        \App\Listeners\HandleWsClientEvents::class,
    ],
];



}
