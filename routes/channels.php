<?php declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;
use App\Models\User;

Broadcast::channel('user.{id}', function (User $user, string $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{roomId}', function (User $user, string $roomId): bool {
    // اگر بعداً membership check بذاری اینجا انجام میشه
    return true;
});

Broadcast::channel('global', function (User $user): array {
    return [
        'id'    => (int) $user->id,
        'name'  => (string) $user->name,
        'email' => (string) $user->email,
    ];
});