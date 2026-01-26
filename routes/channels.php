<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatRoom;

Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    return ChatRoom::where('id', (int)$roomId)
        ->whereHas('users', fn($q) => $q->where('users.id', $user->id))
        ->exists();
});

Broadcast::channel('presence.global', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

// // ✅ private chat channel
// Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
//     // TODO: بعداً membership واقعی رو چک کن
//     return true;
// });

// ✅ private per-user notifications
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
