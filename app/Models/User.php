<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /**
     * @use HasFactory<\Database\Factories\UserFactory>
     */
    use HasFactory;

    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'emailVerifiedAt',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'emailVerifiedAt' => 'datetime',
    ];

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'userId');
    }

    /**
     * @return HasMany<ChatRoomMember, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(ChatRoomMember::class, 'userId');
    }

    /**
     * @return HasMany<RefreshToken, $this>
     */
    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'userId');
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function friendshipsSent(): HasMany
    {
        return $this->hasMany(Friendship::class, 'fromUserId');
    }

    /**
     * @return HasMany<Friendship, $this>
     */
    public function friendshipsReceived(): HasMany
    {
        return $this->hasMany(Friendship::class, 'toUserId');
    }
}