<?php

return [
    'paths' => [
        'api/*',
        'broadcasting/*',      // برای Echo/Reverb
        'sanctum/csrf-cookie', // اگر Sanctum کوکی استفاده شود
    ],

    'allowed_methods' => ['*'],

    // فقط همون فرانت شما:
    'allowed_origins' => ['http://localhost:3000'],

    // اگر پورت/اسکیما ممکنه تغییر کنه، می‌تونی به صورت wildcard هم بذاری:
    // 'allowed_origins' => ['http://localhost:*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // اگر Bearer Token می‌فرستی، نیازی به credentials نیست.
    // اگر کوکی/سشن می‌خواهی بفرستی، این را true کن و تنظیمات Sanctum را هم انجام بده.
    'supports_credentials' => false,
];
