<?php

use Illuminate\Support\Facades\Route;

Route::get('/chatMeetUp/chatrooms', function () {
    // خروجی تست ـ دقیقاً همان چیزی که فرانت انتظار دارد: آرایه‌ای از روم‌ها
    return response()->json([
        ['id' => 1, 'name' => 'General'],
        ['id' => 2, 'name' => 'Developers'],
    ], 200, ['Content-Type' => 'application/json']);
});
