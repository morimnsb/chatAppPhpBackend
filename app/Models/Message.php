<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = ['chat_room_id', 'user_id', 'body', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];

    public function room() {
        return $this->belongsTo(ChatRoom::class, 'chat_room_id');
    }

    public function user() {
        return $this->belongsTo(User::class, 'user_id')->select(['id','first_name','last_name','photo']);
    }
}
