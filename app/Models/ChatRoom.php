<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    protected $fillable = [
        'name',
        'description',
        'last_message_at',
        'is_private',
        'private_key',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'chat_room_user', 'chat_room_id', 'user_id')
            ->withTimestamps();
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'chat_room_id');
    }

    // ✅ اضافه کن
    public function lastMessage()
    {
        return $this->hasOne(Message::class, 'chat_room_id')->latestOfMany();
    }
}
