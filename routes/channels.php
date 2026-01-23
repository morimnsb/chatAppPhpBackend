<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('presence.global', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// ✅ private chat channel
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    // TODO: بعداً membership واقعی رو چک کن
    return true;
});

// ✅ private per-user notifications
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
