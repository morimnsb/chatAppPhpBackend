<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('presence.global', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// اگر چنل چت هم داری مثلا:
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    return ['id' => $user->id, 'name' => $user->name];
});
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});


