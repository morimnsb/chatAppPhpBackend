// app/Services/PresenceService.php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class PresenceService
{
    public static function addUserToRoom(int $roomId, int $userId): void
    {
        Redis::sadd("room:{$roomId}:active_users", (string)$userId);
    }

    public static function removeUserFromRoom(int $roomId, int $userId): void
    {
        Redis::srem("room:{$roomId}:active_users", (string)$userId);
    }

    public static function getRoomUsers(int $roomId): array
    {
        return Redis::smembers("room:{$roomId}:active_users") ?: [];
    }
}
