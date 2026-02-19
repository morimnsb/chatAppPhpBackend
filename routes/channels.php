<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\ChatRoom;

/**
 * Private chat room membership
 * Echo private('chat.{roomId}') => private-chat.{roomId}
 * Channel name here must be WITHOUT "private-"
 */

Broadcast::channel('chat.{roomId}', function ($user, $roomId) {
    $roomId = (int) $roomId;
    if ($roomId <= 0) return false;

    return ChatRoom::query()
        ->whereKey($roomId)
        ->whereHas('users', fn ($q) => $q->whereKey($user->id))
        ->exists();
});


/**
 * Global Presence
 * Echo.join('global') => presence-global
 */
Broadcast::channel('global', function ($user) {
    return [
        'id'   => (int) $user->id,
        'name' => (string) ($user->name ?? ''),
    ];
});

/**
 * Per-user private notifications
 * Echo.private('user.{id}') => private-user.{id}
 */
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

