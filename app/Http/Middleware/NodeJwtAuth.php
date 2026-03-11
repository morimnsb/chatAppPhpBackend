<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

final class NodeJwtAuth
{
    public function handle(Request $request, Closure $next): mixed
    {
        $auth = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['message' => 'Missing token'], 401);
        }

        $token = trim($m[1]);

        try {
            $cfg = config('chat.jwt_secret_shared');
            $secret = is_string($cfg) ? $cfg : '';

            if ($secret === '' || strlen($secret) < 32) {
                return response()->json(['message' => 'JWT secret missing'], 500);
            }

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            $uidRaw = $decoded->uid ?? $decoded->sub ?? 0;
            $uid = is_numeric($uidRaw) ? (int) $uidRaw : 0;

            if ($uid === 0) return response()->json(['message' => 'Invalid token'], 401);

            $user = User::find($uid);
            if (!$user) return response()->json(['message' => 'User not found'], 401);

            auth()->setUser($user);
            $request->setUserResolver(fn () => $user);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unauthorized', 'error' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}