<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    public const KIND_TEXT           = 'text';
    public const KIND_FRIEND_REQUEST = 'friend_request';
    public const KIND_SYSTEM         = 'system';

    protected $fillable = [
        'chat_room_id',
        'user_id',
        'content',
        'kind',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function room()
    {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
