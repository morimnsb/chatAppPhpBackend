<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $from_user_id
 * @property int $to_user_id
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Friendship extends Model
{
    protected $table = 'friendships';

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'status',
    ];
}