<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'User';

    public $timestamps = true;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'email',
        'password',
        'emailVerifiedAt',
        'emailVerifyCode',
        'emailVerifyExp',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'emailVerifyCode',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
        'emailVerifiedAt' => 'datetime',
        'emailVerifyExp' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'userId');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ChatRoomMember::class, 'userId');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(RefreshToken::class, 'userId');
    }

    public function friendshipsSent(): HasMany
    {
        return $this->hasMany(Friendship::class, 'fromUserId');
    }

    public function friendshipsReceived(): HasMany
    {
        return $this->hasMany(Friendship::class, 'toUserId');
    }
}