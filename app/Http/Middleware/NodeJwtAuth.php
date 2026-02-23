<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class NodeJwtAuth
{
    public function handle(Request $request, Closure $next)
    {
        $auth = (string) $request->header('Authorization', '');
        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return response()->json(['message' => 'Missing token'], 401);
        }

        $token = trim($m[1]);

        try {
            $secret = (string) env('JWT_SECRET_SHARED', '');
            if ($secret === '' || strlen($secret) < 32) {
                return response()->json(['message' => 'JWT secret missing'], 500);
            }

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            $uid = (int) ($decoded->uid ?? $decoded->sub ?? 0);
            if (!$uid) return response()->json(['message' => 'Invalid token'], 401);

            $user = User::find($uid);
            if (!$user) return response()->json(['message' => 'User not found'], 401);

            // ✅ این خط باعث میشه broadcasting/auth هم user داشته باشه
            auth()->setUser($user);
            $request->setUserResolver(fn () => $user);

        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unauthorized', 'error' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}