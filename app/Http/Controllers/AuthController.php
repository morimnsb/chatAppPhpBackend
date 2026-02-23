<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Models\Friendship;

use Firebase\JWT\JWT;

class AuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:190',
            'password' => 'required|string|min:6|max:200',
        ]);

        $email = strtolower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'Email already exists'], 409);
        }

        // ✅ ALWAYS bcrypt here (do NOT rely only on mutator)
        $plainPassword = (string) $data['password'];
        $hashedPassword = Hash::make($plainPassword);

        $user = User::create([
            'name'     => trim($data['name']),
            'email'    => $email,
            'password' => $hashedPassword, // ✅ guaranteed bcrypt
        ]);

        // ✅ OTP cache
        $otp = (string) random_int(100000, 999999);
        Cache::put('email_otp_' . $email, $otp, now()->addMinutes(self::OTP_TTL_MINUTES));

        $isDev = app()->environment('local');

        return response()->json([
            'ok'      => true,
            'message' => 'Registered. Please verify email.',
            'user'    => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'otp'     => $isDev ? $otp : null,
        ]);
    }

    public function resendVerifyCode(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($data['email']));

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        if ($user->emailVerifiedAt) {
            return response()->json(['ok' => true, 'message' => 'Already verified']);
        }

        $otp = (string) random_int(100000, 999999);
        Cache::put('email_otp_' . $email, $otp, now()->addMinutes(self::OTP_TTL_MINUTES));

        $isDev = app()->environment('local');

        return response()->json([
            'ok'      => true,
            'message' => 'OTP sent.',
            'otp'     => $isDev ? $otp : null,
        ]);
    }

    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $email = strtolower(trim($data['email']));
        $otp   = (string) $data['otp'];

        $cachedOtp = Cache::get('email_otp_' . $email);

        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP expired or not found'], 422);
        }
        if ((string) $cachedOtp !== (string) $otp) {
            return response()->json(['message' => 'OTP is incorrect'], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        // ✅ Prisma column name
        $user->emailVerifiedAt = now();
        $user->save();

        Cache::forget('email_otp_' . $email);

        // ✅ issue access token
        $access = $this->signAccessToken($user);

        return response()->json([
            'ok'           => true,
            'message'      => 'Email verified',
            'user'         => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'access_token' => $access,
            'token_type'   => 'Bearer',
            'expires_in'   => 15 * 60,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($data['email']));
        $user  = User::where('email', $email)->first();

        if (!$user) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // ✅ Prisma column name
        if (!$user->emailVerifiedAt) {
            return response()->json([
                'message' => 'Email not verified',
                'meta'    => ['code' => 'EMAIL_NOT_VERIFIED'],
            ], 403);
        }

        // ✅ Prevent 500 when password hash is NOT bcrypt
        try {
            $ok = Hash::check((string) $data['password'], (string) $user->password);
        } catch (\Throwable $e) {
            Log::warning('PASSWORD_HASH_INVALID_FORMAT', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'pw_head' => substr((string) $user->password, 0, 12),
                'error'   => $e->getMessage(),
            ]);

            // This is the exact crash you saw: "This password does not use the Bcrypt algorithm."
            return response()->json([
                'message' => 'PASSWORD_HASH_INVALID',
                'meta'    => [
                    'code' => 'PASSWORD_HASH_INVALID',
                    'hint' => 'Stored password is not bcrypt. Re-register user or migrate password hashes.',
                ],
            ], 409);
        }

        if (!$ok) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $access = $this->signAccessToken($user);

        return response()->json([
            'ok'           => true,
            'user'         => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'access_token' => $access,
            'token_type'   => 'Bearer',
            'expires_in'   => 15 * 60,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'ok'   => true,
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function refresh(Request $request)
    {
        return response()->json([
            'ok'      => false,
            'message' => 'Refresh not implemented on Laravel yet',
        ], 501);
    }

    public function users(Request $request)
    {
        $me = $request->user();
        if (!$me) return response()->json([], 401);

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->select('id', 'name', 'email', 'createdAt')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) return response()->json($users);

        $otherIds = $users->pluck('id')->all();

        $friendships = Friendship::query()
            ->where(function ($q) use ($me, $otherIds) {
                $q->where('requester_id', $me->id)
                  ->whereIn('receiver_id', $otherIds);
            })
            ->orWhere(function ($q) use ($me, $otherIds) {
                $q->where('receiver_id', $me->id)
                  ->whereIn('requester_id', $otherIds);
            })
            ->get();

        $friendshipIndex = [];
        foreach ($friendships as $fs) {
            if ((int) $fs->requester_id === (int) $me->id) {
                $otherId = (int) $fs->receiver_id;
                $direction = 'outgoing';
            } else {
                $otherId = (int) $fs->requester_id;
                $direction = 'incoming';
            }

            $base = $fs->status;
            $statusCode = 'none';

            if ($base === 'accepted') {
                $statusCode = 'accepted';
            } elseif ($base === 'pending') {
                $statusCode = $direction === 'outgoing' ? 'pending_outgoing' : 'pending_incoming';
            } else {
                $statusCode = $base;
            }

            $friendshipIndex[$otherId] = [
                'id'        => $fs->id,
                'status'    => $statusCode,
                'raw'       => $fs->status,
                'direction' => $direction,
            ];
        }

        $usersWithFriendship = $users->map(function (User $u) use ($friendshipIndex) {
            $info = $friendshipIndex[(int) $u->id] ?? null;

            return [
                'id'                   => $u->id,
                'name'                 => $u->name,
                'email'                => $u->email,
                'createdAt'            => $u->createdAt,
                'friendship_status'    => $info['status'] ?? 'none',
                'friendship_direction' => $info['direction'] ?? null,
                'friendship_id'        => $info['id'] ?? null,
            ];
        });

        return response()->json($usersWithFriendship);
    }

    private function signAccessToken(User $user): string
    {
        $secret = (string) env('JWT_SECRET_SHARED', '');
        $aud = (string) env('JWT_AUDIENCE', 'chatapp');
        $iss = (string) env('JWT_ISSUER', 'laravel');

        if ($secret === '' || strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET_SHARED missing or too short (>= 32 chars)');
        }

        $now = time();
        $exp = $now + (15 * 60);

        $payload = [
            'iss'   => $iss,
            'aud'   => $aud,
            'iat'   => $now,
            'exp'   => $exp,
            'sub'   => (string) $user->id,
            'uid'   => (int) $user->id,
            'email' => $user->email,
            'name'  => $user->name,
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}