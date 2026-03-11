<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $private_key
 * @property array<string, mixed>|null $private_meta
 * @property bool|int|null $is_group
 */
class ChatRoom extends Model
{
    protected $table = 'chat_rooms';

    protected $fillable = [
        'name',
        'private_key',
        'private_meta',
        'is_group',
    ];

    protected $casts = [
        'private_meta' => 'array',
        'is_group' => 'boolean',
    ];

    /**
     * @return HasMany<ChatRoomMember, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(ChatRoomMember::class, 'room_id');
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'room_id');
    }
}