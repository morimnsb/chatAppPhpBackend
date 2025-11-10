<?php

use Illuminate\Support\Facades\Broadcast;

// برای پیام‌های روم (private)
Broadcast::channel('room.{roomId}', function ($user, $roomId) {
    // اگر مجازسازی خاصی ندارید، موقتاً true
    return true;
});

// برای حضور اعضا (presence)
Broadcast::channel('presence.room.{roomId}', function ($user, $roomId) {
    return [
        'id'   => $user->id,
        'name' => $user->name,
    ];
});

