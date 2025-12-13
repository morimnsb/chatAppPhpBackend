<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    | این بخش مربوط به خود سرور WebSocket است که با
    | php artisan reverb:start
    | اجرا می‌کنی.
    |
    */

    'servers' => [

        'reverb' => [
            // آدرس و پورت سِروری که reverb:start روی آن گوش می‌دهد
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', env('REVERB_PORT', 8080)),
            'path' => env('REVERB_SERVER_PATH', ''),

            // hostname که کلاینت‌ها استفاده می‌کنند (برای URL)
            'hostname' => env('REVERB_HOST', '127.0.0.1'),

            'options' => [
                'tls' => [], // برای لوکال خالی می‌ماند (بدون TLS)
            ],

            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),

            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url'      => env('REDIS_URL'),
                    'host'     => env('REDIS_HOST', '127.0.0.1'),
                    'port'     => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                    'timeout'  => env('REDIS_TIMEOUT', 60),
                ],
            ],

            'pulse_ingest_interval'     => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    |
    | این بخش مربوط به app key / secret / id است که کلاینت‌ها (Echo + Pusher)
    | برای اتصال استفاده می‌کنند.
    |
    */

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key'    => env('REVERB_APP_KEY', 'local'),
                'secret' => env('REVERB_APP_SECRET', 'secret'),
                'app_id' => env('REVERB_APP_ID', 'app-id'),

                'options' => [
                    // این host/port همانی است که front به آن وصل می‌شود
                    'host'   => env('REVERB_HOST', '127.0.0.1'),
                    'port'   => env('REVERB_PORT', 8080),
                    'scheme' => env('REVERB_SCHEME', 'http'),
                    'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
                ],

                // در dev می‌گذاریم *، بعداً می‌توانی محدودش کنی به frontend
                'allowed_origins' => ['*'],

                'ping_interval'     => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout'  => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_connections'   => env('REVERB_APP_MAX_CONNECTIONS'),
                'max_message_size'  => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],

    ],

];
