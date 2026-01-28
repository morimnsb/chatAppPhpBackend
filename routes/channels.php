<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatRoom;

/*
|--------------------------------------------------------------------------
| Chat room (private)
|--------------------------------------------------------------------------
| private-chat.{roomId}
| فقط اعضای آن روم اجازه subscribe دارند
*/
Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    return ChatRoom::query()
        ->where('id', (int) $roomId)
        ->whereHas('users', fn ($q) => $q->where('users.id', $user->id))
        ->exists();
});

/*
|--------------------------------------------------------------------------
| Global presence
|--------------------------------------------------------------------------
| presence.global
| فقط برای نمایش online users
*/
Broadcast::channel('presence.global', function ($user) {
    return [
        'id'   => (int) $user->id,
        'name' => (string) $user->name,
    ];
});

/*
|--------------------------------------------------------------------------
| Per-user private notifications
|--------------------------------------------------------------------------
| private-user.{id}
| فقط خود یوزر اجازه دارد
*/
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
