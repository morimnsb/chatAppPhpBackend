<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
        'otp_code',
        'otp_expires_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected $casts = [
        'otp_expires_at'    => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    // ✅ Advanced: use modern mutator + prevents double-hash safely
    public function setPasswordAttribute($value): void
    {
        if (!is_string($value) || $value === '') return;

        $this->attributes['password'] =
            Hash::info($value)['algoName'] !== 'unknown'
                ? $value
                : Hash::make($value);
    }

    // ✅ Optional: always have name
    public function getNameAttribute($value): string
    {
        if (is_string($value) && $value !== '') return $value;

        $fn = (string) ($this->attributes['first_name'] ?? '');
        $ln = (string) ($this->attributes['last_name'] ?? '');
        $full = trim($fn.' '.$ln);

        return $full !== '' ? $full : 'User#'.$this->id;
    }

    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_user')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
