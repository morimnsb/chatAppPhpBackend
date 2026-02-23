<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    // اگر خواستی بعداً membership check بذاری؛ فعلاً true
    return true;
});

Broadcast::channel('global', function ($user) {
    // presence needs user info
    return [
        'id' => (int) $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ];
});