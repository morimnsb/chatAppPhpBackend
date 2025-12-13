<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    if (! $user) return false;
    if ((int) $user->id !== (int) $userId) return false;

    return [
        'id'    => $user->id,
        'name'  => $user->name,
        'email' => $user->email,
    ];
});

