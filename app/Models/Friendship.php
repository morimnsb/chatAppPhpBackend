<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Friendship extends Model
{
    use HasFactory;

    protected $fillable = [
        'requester_id',
        'receiver_id',
        'status',
        'accepted_at',
    ];

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function scopeBetween($query, $userA, $userB)
    {
        $a = (int) $userA;
        $b = (int) $userB;

        return $query->where(function ($q) use ($a, $b) {
            $q->where('requester_id', $a)
              ->where('receiver_id', $b);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('requester_id', $b)
              ->where('receiver_id', $a);
        });
    }
}
