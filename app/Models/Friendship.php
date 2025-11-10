<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Friendship extends Model
{
    protected $fillable = [
        'from_user_id', 'to_user_id', 'user_low_id', 'user_high_id',
    ];
}
