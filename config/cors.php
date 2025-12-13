<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // برای JWT معمولاً false هم اوکیه چون کوکی نمی‌خوایم.
    // ولی اگر بعداً خواستی کوکی هم داشته باشی، true کن.
    'supports_credentials' => false,
];
