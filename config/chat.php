<?php

return [
    'jwt_secret_shared' => env('JWT_SECRET_SHARED', ''),
    'jwt_audience'      => env('JWT_AUDIENCE', 'chatapp'),
    'jwt_issuer'        => env('JWT_ISSUER', 'laravel'),
];