<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends Model
{
    protected $table = 'ChatRoom';

    public $timestamps = true;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = 'updatedAt';

    protected $fillable = [
        'name',
        'isGroup',
        'privateKey',
    ];

    protected $casts = [
        'isGroup' => 'boolean',
        'createdAt' => 'datetime',
        'updatedAt' => 'datetime',
    ];

    public function members(): HasMany
    {
        return $this->hasMany(ChatRoomMember::class, 'roomId');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'roomId');
    }
}