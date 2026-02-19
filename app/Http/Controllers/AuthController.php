<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;

use App\Models\User;
use App\Models\Friendship;

class AuthController extends Controller
{
    private const OTP_TTL_MINUTES = 10;

    public function register(Request $request)
{
    // ✅ accept Node-style fields too
    $data = $request->validate([
        'first_name' => ['nullable', 'string', 'max:255'],
        'last_name'  => ['nullable', 'string', 'max:255'],
        'name'       => ['nullable', 'string', 'max:255'],

        'email'      => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

        'password'   => ['required', 'string', 'min:8'],
        'password2'  => ['nullable', 'string'],
        'password_confirmation' => ['nullable', 'string'],
    ]);

    // normalize email like Node
    $email = strtolower(trim($data['email']));

    // pick password2 from multiple possible fields
    $password2 = $data['password2']
        ?? $data['password_confirmation']
        ?? null;

    // ---- build first/last from name if needed
    $first = trim((string)($data['first_name'] ?? ''));
    $last  = trim((string)($data['last_name'] ?? ''));

    if ((!$first || !$last) && !empty($data['name'])) {
        $full = preg_replace('/\s+/', ' ', trim((string)$data['name']));
        $parts = $full ? explode(' ', $full) : [];
        if (!$first) $first = array_shift($parts) ?? '';
        if (!$last)  $last  = trim(implode(' ', $parts));
    }

    // ---- manual validation errors to match Node
    $errors = [];

    if (!$first) $errors['first_name'] = ['First name is required.'];
    if (!$last)  $errors['last_name']  = ['Last name is required.'];
    if (!$password2) $errors['password2'] = ['Repeat password is required.'];
    if (!empty($data['password']) && $password2 && $data['password'] !== $password2) {
        $errors['password2'] = ['Passwords do not match.'];
    }

    if (!empty($errors)) {
        return response()->json(['errors' => $errors], 422);
    }

    $name = trim($first . ' ' . $last);

    $user = User::create([
        'name'     => $name,
        'email'    => $email,
        'password' => Hash::make($data['password']),
    ]);

    $otp = random_int(100000, 999999);
    Cache::put('email_otp_' . $user->email, $otp, now()->addMinutes(self::OTP_TTL_MINUTES));

    $response = [
        'ok'      => true,
        'message' => 'Registered successfully. Please verify your email.',
        'user'    => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at,
        ],
    ];

    // ✅ like Node: return otp in local for testing
    if (app()->environment('local')) {
        $response['otp'] = $otp;
    }

    return response()->json($response, 201);
}


    public function verifyEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $email = $data['email'];
        $otp   = $data['otp'];

        $cachedOtp = Cache::get('email_otp_' . $email);

        if (!$cachedOtp) {
            return response()->json(['message' => 'OTP منقضی شده یا وجود ندارد.'], 422);
        }

        if ((string) $cachedOtp !== (string) $otp) {
            return response()->json(['message' => 'OTP اشتباه است.'], 422);
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            return response()->json(['message' => 'کاربر پیدا نشد.'], 404);
        }

        $user->email_verified_at = now();
        $user->save();

        Cache::forget('email_otp_' . $email);

        $token = $user->createToken('access')->plainTextToken;

        return response()->json([
            'message'      => 'ایمیل با موفقیت تأیید شد.',
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        $token = $user->createToken('access')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'user'         => $user,
        ]);
    }

    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function users(Request $request)
    {
        $me = $request->user();
        if (!$me) return response()->json([], 401);

        $users = User::query()
            ->where('id', '!=', $me->id)
            ->select('id', 'name', 'email', 'created_at')
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
                'created_at'           => $u->created_at,
                'friendship_status'    => $info['status'] ?? 'none',
                'friendship_direction' => $info['direction'] ?? null,
                'friendship_id'        => $info['id'] ?? null,
                'friendship_raw'       => $info['raw'] ?? null,
            ];
        });

        return response()->json($usersWithFriendship);
    }
}
