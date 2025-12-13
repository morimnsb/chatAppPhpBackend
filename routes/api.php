<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

use App\Models\User;
use App\Models\Friendship;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Events\ChatMessageCreated;

// ---- تنظیمات OTP ----
if (! defined('OTP_TTL_MINUTES')) {
    define('OTP_TTL_MINUTES', 10);
}

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

// ✅ POST /api/auth/register
Route::post('/auth/register', function (Request $request) {
    $data = $request->validate([
        'name'     => ['required', 'string', 'max:255'],
        'email'    => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ]);

    $user = User::create([
        'name'     => $data['name'],
        'email'    => $data['email'],
        'password' => Hash::make($data['password']),
    ]);

    $otp = random_int(100000, 999999);
    Cache::put('email_otp_' . $user->email, $otp, now()->addMinutes(OTP_TTL_MINUTES));

    $response = [
        'message' => 'ثبت‌نام انجام شد. لطفاً ایمیل را با OTP تأیید کنید.',
        'email'   => $user->email,
    ];

    if (app()->environment('local')) {
        $response['otp'] = $otp;
    }

    return response()->json($response, 201);
});

// ✅ POST /api/auth/verify-email
Route::post('/auth/verify-email', function (Request $request) {
    $data = $request->validate([
        'email' => ['required', 'email'],
        'otp'   => ['required', 'digits:6'],
    ]);

    $email = $data['email'];
    $otp   = $data['otp'];

    $cachedOtp = Cache::get('email_otp_' . $email);

    if (! $cachedOtp) {
        return response()->json(['message' => 'OTP منقضی شده یا وجود ندارد.'], 422);
    }

    if ((string) $cachedOtp !== (string) $otp) {
        return response()->json(['message' => 'OTP اشتباه است.'], 422);
    }

    $user = User::where('email', $email)->first();
    if (! $user) {
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
});

// ✅ Login: POST /api/auth/login
Route::post('/auth/login', function (Request $request) {
    $data = $request->validate([
        'email'    => ['required', 'email'],
        'password' => ['required'],
    ]);

    $user = User::where('email', $data['email'])->first();

    if (! $user || ! Hash::check($data['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 422);
    }

    $token = $user->createToken('access')->plainTextToken;

    return response()->json([
        'access_token' => $token,
        'token_type'   => 'Bearer',
        'user'         => $user,
    ]);
});

// ✅ ME: GET /api/auth/me
Route::get('/auth/me', function (Request $request) {
    return response()->json($request->user());
})->middleware('auth:sanctum');

// ✅ Logout: POST /api/auth/logout
Route::post('/auth/logout', function (Request $request) {
    $request->user()?->currentAccessToken()?->delete();
    return response()->json(['ok' => true]);
})->middleware('auth:sanctum');

// ✅ GET /api/auth/users → همراه وضعیت دوستی
Route::get('/auth/users', function (Request $request) {
    /** @var \App\Models\User|null $me */
    $me = $request->user();

    if (! $me) {
        return response()->json([], 401);
    }

    $users = User::query()
        ->where('id', '!=', $me->id)
        ->select('id', 'name', 'email', 'created_at')
        ->orderBy('id')
        ->get();

    if ($users->isEmpty()) {
        return response()->json($users);
    }

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
            $otherId   = (int) $fs->receiver_id;
            $direction = 'outgoing';
        } else {
            $otherId   = (int) $fs->requester_id;
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
            'friendship_status'    => $info['status']    ?? 'none',
            'friendship_direction' => $info['direction'] ?? null,
            'friendship_id'        => $info['id']        ?? null,
            'friendship_raw'       => $info['raw']       ?? null,
        ];
    });

    return response()->json($usersWithFriendship);
})->middleware('auth:sanctum');


/*
|--------------------------------------------------------------------------|
| Chat / chatMeetUp Routes (همه با auth:sanctum)
|--------------------------------------------------------------------------|
*/

Route::middleware('auth:sanctum')->group(function () {

    // ✅ GET /api/chatMeetUp/chatrooms  (برای اینکه فرانت 500/404 نگیره)
    Route::get('/chatMeetUp/chatrooms', function (Request $request) {
        /** @var \App\Models\User $me */
        $me = $request->user();

        $rooms = ChatRoom::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->with(['users:id,name,email', 'lastMessage.user:id,name,email'])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json($rooms);
    });

    // ✅ GET /api/chatMeetUp/conversations
    Route::get('/chatMeetUp/conversations', function (Request $request) {
        /** @var \App\Models\User $me */
        $me = $request->user();

        try {
            $rooms = ChatRoom::query()
                ->where('is_private', true)
                ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
                ->with([
                    'users' => fn ($q) => $q->where('users.id', '!=', $me->id)->select('users.id','users.name','users.email'),
                    'lastMessage',
                ])
                ->orderByDesc('last_message_at')
                ->get();

            $partners = $rooms->map(function (ChatRoom $room) use ($me) {
                $partner = $room->users->first();
                $lastMsg = $room->lastMessage;

                $friendship = null;
                if ($partner) {
                    $friendship = Friendship::where(function ($q) use ($me, $partner) {
                            $q->where('requester_id', $me->id)->where('receiver_id', $partner->id);
                        })
                        ->orWhere(function ($q) use ($me, $partner) {
                            $q->where('requester_id', $partner->id)->where('receiver_id', $me->id);
                        })
                        ->first();
                }

                $friendshipId        = $friendship?->id;
                $friendshipRaw       = $friendship?->status;
                $friendshipStatus    = 'none';
                $friendshipDirection = null;

                if ($friendship) {
                    if ($friendship->status === 'accepted') {
                        $friendshipStatus    = 'accepted';
                        $friendshipDirection = 'mutual';
                    } elseif ($friendship->status === 'pending') {
                        if ((int) $friendship->requester_id === (int) $me->id) {
                            $friendshipStatus    = 'pending_outgoing';
                            $friendshipDirection = 'outgoing';
                        } else {
                            $friendshipStatus    = 'pending_incoming';
                            $friendshipDirection = 'incoming';
                        }
                    } else {
                        $friendshipStatus = $friendship->status;
                    }
                }

                return [
                    'id'              => $partner?->id,
                    'first_name'      => $partner?->name ?? $partner?->email ?? 'Unknown',
                    'last_name'       => null,
                    'room_id'         => $room->id,
                    'last_message'    => $lastMsg?->content ?? '',
                    'last_message_at' => optional($lastMsg?->created_at)->toDateTimeString(),
                    'friendship_id'        => $friendshipId,
                    'friendship_raw'       => $friendshipRaw,
                    'friendship_status'    => $friendshipStatus,
                    'friendship_direction' => $friendshipDirection,
                ];
            })->values();

            return response()->json([
                'partners' => $partners,
                'groups'   => [],
            ]);
        } catch (\Throwable $e) {
            Log::error('conversations failed', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile(),
                'line'  => $e->getLine(),
            ]);

            return response()->json(['message' => 'Server Error'], 500);
        }
    });

    // ✅ GET /api/chatMeetUp/messages/{room}
    Route::get('/chatMeetUp/messages/{room}', function (Request $request, $room) {
        /** @var \App\Models\User $me */
        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->first();

        if (! $chatRoom) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $messages = $chatRoom->messages()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json(['room_id' => $chatRoom->id, 'messages' => $messages]);
    });

    // ✅ POST /api/chatMeetUp/messages/{room}
    Route::post('/chatMeetUp/messages/{room}', function (Request $request, $room) {
        $data = $request->validate(['content' => ['required', 'string']]);

        /** @var \App\Models\User $me */
        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->first();

        if (! $chatRoom) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $message = $chatRoom->messages()->create([
            'user_id' => $me->id,
            'content' => $data['content'],
            'kind'    => Message::KIND_TEXT,
        ]);

        $chatRoom->update(['last_message_at' => now()]);

        try {
            event(new ChatMessageCreated($chatRoom->id, $message));
        } catch (\Throwable $e) {
            Log::error('Broadcast ChatMessageCreated failed (send message)', [
                'msg_id' => $message->id,
                'error'  => $e->getMessage(),
            ]);
        }

        return response()->json(['message' => $message->load('user:id,name,email')], 201);
    });

});

