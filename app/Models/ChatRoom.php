<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatRoom extends Model
{
    protected $fillable = ['name','is_private','private_key'];

    public function messages() {
        return $this->hasMany(Message::class, 'chat_room_id');
    }

    public function users() {
        return $this->belongsToMany(User::class, 'chat_room_user', 'chat_room_id', 'user_id');
    }
}
