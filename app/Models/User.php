<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

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

    public function setPasswordAttribute($val)
{
    if (is_string($val) && preg_match('/^\$2[ayb]\$[0-9]{2}\$/', $val)) {
        // قبلاً bcrypt شده؛ همان را ذخیره کن
        $this->attributes['password'] = $val;
    } else {
        // هر ورودی دیگری را bcrypt کن
        $this->attributes['password'] = Hash::make($val);
    }
}

    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }
    // app/Models/User.php
public function chatRooms()
{
    return $this->belongsToMany(\App\Models\ChatRoom::class, 'chat_room_user')
                ->withTimestamps();
}
public function messages()
{
    return $this->hasMany(Message::class);
}

}
