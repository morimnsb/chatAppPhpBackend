<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

final class AuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    private function asString(mixed $v, string $default = ''): string
    {
        if (is_string($v)) return $v;
        if (is_int($v) || is_float($v) || is_bool($v)) return (string) $v;
        return $default;
    }

    private function iso(mixed $dt): ?string
    {
        return $dt instanceof \Carbon\CarbonInterface ? $dt->toIso8601String() : null;
    }

    /** @return array{id:int,name:string,email:string,createdAt:?string,verifiedAt:?string} */
    private function shapeUser(User $u): array
    {
        return [
            'id'         => (int) $u->id,
            'name'       => $this->asString($u->name),
            'email'      => $this->asString($u->email),
            'createdAt'  => $this->iso($u->created_at),
            'verifiedAt' => is_string($u->email_verified_at) ? $u->email_verified_at : null,
        ];
    }

    public function register(Request $request): JsonResponse
    {
        /** @var array{name:string,email:string,password:string} $data */
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:190',
            'password' => 'required|string|min:6|max:200',
        ]);

        $email = strtolower(trim($this->asString($data['email'])));

        if (User::where('email', $email)->exists()) {
            return response()->json(['message' => 'Email already exists'], 409);
        }

        $plainPassword  = $this->asString($data['password']);
        $hashedPassword = Hash::make($plainPassword);

        $user = User::create([
    'name'     => trim($this->asString($data['name'])),
    'email'    => $email,
    'password' => $hashedPassword,
        ]);

        $otp = (string) random_int(100000, 999999);
        Cache::put('email_otp_' . $email, $otp, now()->addMinutes(self::OTP_TTL_MINUTES));

        $isDev = app()->environment('local');

        return response()->json([
            'ok'      => true,
            'message' => 'Registered. Please verify email.',
            'user'    => $this->shapeUser($user),
            'otp'     => $isDev ? $otp : null,
        ]);
    }

    public function resendVerifyCode(Request $request): JsonResponse
    {
        /** @var array{email:string} $data */
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($this->asString($data['email'])));

        $user = User::where('email', $email)->first();
        if (!$user) return response()->json(['message' => 'User not found'], 404);

        if ($user->email_verified_at !== null) {
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

    public function verifyEmail(Request $request): JsonResponse
{
    /** @var array{email:string,otp:string} $data */
    $data = $request->validate([
        'email' => ['required', 'email'],
        'otp'   => ['required', 'digits:6'],
    ]);

    $email = strtolower(trim($this->asString($data['email'])));
    $otp   = $this->asString($data['otp']);

    $cachedOtp = Cache::get('email_otp_' . $email);

    if (!is_string($cachedOtp) || $cachedOtp === '') {
        return response()->json(['message' => 'OTP expired or not found'], 422);
    }
    if ($cachedOtp !== $otp) {
        return response()->json(['message' => 'OTP is incorrect'], 422);
    }

    $user = User::where('email', $email)->first();
    if (!$user instanceof User) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // ✅ چون ستون string|null هست، string ذخیره می‌کنیم
    $user->email_verified_at = $this->iso(now());
    $user->save();

    Cache::forget('email_otp_' . $email);

    $access = $this->signAccessToken($user);

    return response()->json([
        'ok'           => true,
        'message'      => 'Email verified',
        'user'         => $this->shapeUser($user),
        'access_token' => $access,
        'token_type'   => 'Bearer',
        'expires_in'   => 15 * 60,
    ]);
}

    public function login(Request $request): JsonResponse
    {
        /** @var array{email:string,password:string} $data */
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower(trim($this->asString($data['email'])));
        $user  = User::where('email', $email)->first();

        if (!$user) return response()->json(['message' => 'Invalid credentials'], 401);

        if ($user->email_verified_at === null) {
            return response()->json([
                'message' => 'Email not verified',
                'meta'    => ['code' => 'EMAIL_NOT_VERIFIED'],
            ], 403);
        }

        try {
            $ok = Hash::check(
                $this->asString($data['password']),
                $this->asString($user->password)
            );
        } catch (\Throwable $e) {
            Log::warning('PASSWORD_HASH_INVALID_FORMAT', [
                'user_id' => (int) $user->id,
                'email'   => $this->asString($user->email),
                'pw_head' => substr($this->asString($user->password), 0, 12),
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'PASSWORD_HASH_INVALID',
                'meta'    => [
                    'code' => 'PASSWORD_HASH_INVALID',
                    'hint' => 'Stored password is not bcrypt. Re-register user or migrate password hashes.',
                ],
            ], 409);
        }

        if (!$ok) return response()->json(['message' => 'Invalid credentials'], 401);

        $access = $this->signAccessToken($user);

        return response()->json([
            'ok'           => true,
            'user'         => $this->shapeUser($user),
            'access_token' => $access,
            'token_type'   => 'Bearer',
            'expires_in'   => 15 * 60,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $u = $request->user();
        if (!$u instanceof User) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 401);
        }

        return response()->json([
            'ok'   => true,
            'user' => $this->shapeUser($u),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        return response()->json(['ok' => true]);
    }

    public function refresh(Request $request): JsonResponse
    {
        return response()->json([
            'ok'      => false,
            'message' => 'Refresh not implemented on Laravel yet',
        ], 501);
    }

    public function users(Request $request): JsonResponse
    {
        $me = $request->user();
        if (!$me instanceof User) return response()->json([], 401);

        $users = User::query()
            ->where('id', '!=', (int) $me->id)
            ->select('id', 'name', 'email', 'created_at')
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) return response()->json($users);

        /** @var array<int,int> $otherIds */
        $otherIds = $users->pluck('id')
            ->map(fn (mixed $x): int => is_numeric($x) ? (int) $x : 0)
            ->filter()
            ->values()
            ->all();

        $friendships = Friendship::query()
            ->where(function ($q) use ($me, $otherIds) {
                $q->where('requester_id', (int) $me->id)
                    ->whereIn('receiver_id', $otherIds);
            })
            ->orWhere(function ($q) use ($me, $otherIds) {
                $q->where('receiver_id', (int) $me->id)
                    ->whereIn('requester_id', $otherIds);
            })
            ->get();

        /** @var array<int, array{id:int,status:string,raw:string,direction:string}> $friendshipIndex */
        $friendshipIndex = [];

        foreach ($friendships as $fs) {
            if ((int) $fs->requester_id === (int) $me->id) {
                $otherId   = (int) $fs->receiver_id;
                $direction = 'outgoing';
            } else {
                $otherId   = (int) $fs->requester_id;
                $direction = 'incoming';
            }

            $base = $this->asString($fs->status);
            $statusCode = 'none';

            if ($base === 'accepted') {
                $statusCode = 'accepted';
            } elseif ($base === 'pending') {
                $statusCode = $direction === 'outgoing' ? 'pending_outgoing' : 'pending_incoming';
            } else {
                $statusCode = $base;
            }

            $friendshipIndex[$otherId] = [
                'id'        => (int) $fs->id,
                'status'    => $statusCode,
                'raw'       => $base,
                'direction' => $direction,
            ];
        }

        $usersWithFriendship = $users->map(function (User $u) use ($friendshipIndex): array {
            $info = $friendshipIndex[(int) $u->id] ?? null;

            if (is_array($info)) {
                // ✅ offsets همیشه موجودن -> دیگه ?? لازم نیست
                $status    = $info['status'];
                $direction = $info['direction'];
                $fid       = $info['id'];
            } else {
                $status = 'none';
                $direction = null;
                $fid = null;
            }

            return [
                'id'                   => (int) $u->id,
                'name'                 => $this->asString($u->name),
                'email'                => $this->asString($u->email),
                'createdAt'            => $this->iso($u->created_at),
                'friendship_status'    => $status,
                'friendship_direction' => $direction,
                'friendship_id'        => $fid,
            ];
        });

        return response()->json($usersWithFriendship);
    }

    private function signAccessToken(User $user): string
{
    $secret = $this->asString(config('chat.jwt_secret_shared'), '');
    $aud    = $this->asString(config('chat.jwt_audience'), 'chatapp');
    $iss    = $this->asString(config('chat.jwt_issuer'), 'laravel');

    if ($secret === '' || strlen($secret) < 32) {
        throw new \RuntimeException('JWT secret missing or too short (>= 32 chars)');
    }

    $now = time();
    $exp = $now + (15 * 60);

    $payload = [
        'iss'   => $iss,
        'aud'   => $aud,
        'iat'   => $now,
        'exp'   => $exp,
        'sub'   => (string) (int) $user->id,
        'uid'   => (int) $user->id,
        'email' => $this->asString($user->email),
        'name'  => $this->asString($user->name),
    ];

    return JWT::encode($payload, $secret, 'HS256');
}
}