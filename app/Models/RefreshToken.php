<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    protected $table = 'RefreshToken';

    public $timestamps = true;
    const CREATED_AT = 'createdAt';
    const UPDATED_AT = null; // Prisma doesn't have updatedAt here

    protected $fillable = [
        'userId',
        'tokenHash',
        'revokedAt',
        'expiresAt',
        'createdAt',
    ];

    protected $casts = [
        'createdAt' => 'datetime',
        'revokedAt' => 'datetime',
        'expiresAt' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId');
    }
}