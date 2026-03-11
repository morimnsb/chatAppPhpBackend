<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $fillable = [
        'userId',
        'token',
        'expiresAt',
        'revokedAt',
    ];

    protected $casts = [
        'userId'    => 'int',
        'expiresAt' => 'datetime',
        'revokedAt' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}