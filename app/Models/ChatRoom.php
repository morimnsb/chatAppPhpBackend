<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatRoom extends Model
{
    protected $fillable = [
        'name',
        'description',
        'last_message_at',
        'is_private',
        'private_key',
    ];

    protected $casts = [
        'is_private'       => 'boolean',
        'last_message_at'  => 'datetime',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_user', 'chat_room_id', 'user_id')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'chat_room_id');
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class, 'chat_room_id')->latestOfMany();
    }

    // ✅ Advanced helper for channels/policies
    public function hasMember(int $userId): bool
    {
        return $this->users()->whereKey($userId)->exists();
    }
}
