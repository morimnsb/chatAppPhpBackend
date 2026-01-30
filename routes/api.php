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
use App\Events\ChatGlobalNotify;

// ---- OTP config ----
if (!defined('OTP_TTL_MINUTES')) {
    define('OTP_TTL_MINUTES', 10);
}

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/

// POST /api/auth/register
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

// POST /api/auth/verify-email
Route::post('/auth/verify-email', function (Request $request) {
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

    if ((string)$cachedOtp !== (string)$otp) {
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
});

// POST /api/auth/login
Route::post('/auth/login', function (Request $request) {
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
});

// GET /api/auth/me
Route::get('/auth/me', function (Request $request) {
    return response()->json($request->user());
})->middleware('auth:sanctum');

// POST /api/auth/logout
Route::post('/auth/logout', function (Request $request) {
    $request->user()?->currentAccessToken()?->delete();
    return response()->json(['ok' => true]);
})->middleware('auth:sanctum');

// GET /api/auth/users
Route::get('/auth/users', function (Request $request) {
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
        if ((int)$fs->requester_id === (int)$me->id) {
            $otherId = (int)$fs->receiver_id;
            $direction = 'outgoing';
        } else {
            $otherId = (int)$fs->requester_id;
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
        $info = $friendshipIndex[(int)$u->id] ?? null;

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
})->middleware('auth:sanctum');


/*
|--------------------------------------------------------------------------
| Chat / chatMeetUp Routes (auth:sanctum)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // helper: unify message payload (ONE format everywhere)
    $shapeMessage = function (Message $m) {
        $m->loadMissing('user:id,name,email'); // no photo (sqlite safe)

        return [
            'id'           => (int)$m->id,
            'chat_room_id' => (int)$m->chat_room_id,
            'user_id'      => (int)$m->user_id,
            'content'      => $m->content ?? null,
            'kind'         => $m->kind ?? null,
            'created_at'   => optional($m->created_at)->toIso8601String(),
            'updated_at'   => optional($m->updated_at)->toIso8601String(),
            'user'         => $m->user ? [
                'id'    => (int)$m->user->id,
                'name'  => (string)$m->user->name,
                'email' => (string)$m->user->email,
            ] : null,
        ];
    };

    // POST /api/chatMeetUp/friendship
    Route::post('/chatMeetUp/friendship', function (Request $request) use ($shapeMessage) {
        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'content'    => ['nullable', 'string'],
        ]);

        $from = $request->user();
        $to   = User::find($data['to_user_id']);

        if (!$to) return response()->json(['message' => 'User not found'], 404);

        if ((int)$from->id === (int)$to->id) {
            return response()->json(['message' => 'نمی‌توانید خودتان را اضافه کنید.'], 422);
        }

        $existing = Friendship::where(function ($q) use ($from, $to) {
                $q->where('requester_id', $from->id)->where('receiver_id', $to->id);
            })
            ->orWhere(function ($q) use ($from, $to) {
                $q->where('requester_id', $to->id)->where('receiver_id', $from->id);
            })
            ->first();

        if ($existing) {
            return response()->json([
                'message'    => 'درخواست دوستی قبلاً ثبت شده است.',
                'friendship' => $existing,
            ], 200);
        }

        return DB::transaction(function () use ($from, $to, $data, $shapeMessage) {
            $friendship = Friendship::create([
                'requester_id' => $from->id,
                'receiver_id'  => $to->id,
                'status'       => 'pending',
            ]);

            $existingRoomRow = DB::table('chat_rooms as r')
                ->join('chat_room_user as cru1', 'cru1.chat_room_id', '=', 'r.id')
                ->join('chat_room_user as cru2', 'cru2.chat_room_id', '=', 'r.id')
                ->where('r.is_private', true)
                ->where('cru1.user_id', $from->id)
                ->where('cru2.user_id', $to->id)
                ->select('r.id')
                ->first();

            if ($existingRoomRow) {
                $room = ChatRoom::find($existingRoomRow->id);
            } else {
                $room = ChatRoom::create([
                    'name'        => null,
                    'description' => null,
                    'is_private'  => true,
                    'private_key' => (string) Str::uuid(),
                ]);
                $room->users()->attach([$from->id, $to->id]);
            }

            $content = trim($data['content'] ?? '') ?: 'سلام! من برایت درخواست دوستی فرستادم 🙌';

            $friendKind = defined(Message::class.'::KIND_FRIEND_REQUEST')
                ? Message::KIND_FRIEND_REQUEST
                : 'friend_request';

            $message = $room->messages()->create([
                'user_id' => $from->id,
                'content' => $content,
                'kind'    => $friendKind,
            ]);

            try {
                event(new ChatMessageCreated($room->id, $message));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (friend_request msg)', [
                    'msg_id' => $message->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message'    => 'Friend request sent.',
                'friendship' => $friendship,
                'room'       => $room->load('users:id,name,email'),
                'dm_message' => $shapeMessage($message),
            ], 201);
        });
    });

    // POST /api/chatMeetUp/friendship/respond
    Route::post('/chatMeetUp/friendship/respond', function (Request $request) {
        $me = $request->user();

        $data = $request->validate([
            'friendship_id' => ['required', 'integer', 'exists:friendships,id'],
            'action'        => ['required', Rule::in(['accept', 'reject'])],
        ]);

        $friendship = Friendship::findOrFail($data['friendship_id']);

        if ((int)$friendship->receiver_id !== (int)$me->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $friendship->status = $data['action'] === 'accept' ? 'accepted' : 'rejected';
        $friendship->save();

        return response()->json([
            'message'        => 'Friend request updated.',
            'friendship_id'  => $friendship->id,
            'friendship_raw' => $friendship->status,
        ]);
    });

    // GET /api/chatMeetUp/chatrooms
    Route::get('/chatMeetUp/chatrooms', function (Request $request) {
        $me = $request->user();

        $rooms = ChatRoom::query()
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->with([
                'users:id,name,email',
                'lastMessage.user:id,name,email',
            ])
            ->orderByDesc('last_message_at')
            ->get();

        return response()->json($rooms);
    });

    // GET /api/chatMeetUp/conversations
    Route::get('/chatMeetUp/conversations', function (Request $request) {
        $me = $request->user();

        try {
            $rooms = ChatRoom::query()
                ->where('is_private', true)
                ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
                ->with([
                    'users:id,name,email',
                    'lastMessage.user:id,name,email',
                ])
                ->orderByDesc('last_message_at')
                ->get();

            $partners = $rooms->map(function (ChatRoom $room) use ($me) {
                $partner = $room->users->firstWhere('id', '!=', $me->id);
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

                $friendshipId  = $friendship?->id;
                $friendshipRaw = $friendship?->status;

                $friendshipStatus    = 'none';
                $friendshipDirection = null;

                if ($friendship) {
                    if ($friendship->status === 'accepted') {
                        $friendshipStatus    = 'accepted';
                        $friendshipDirection = 'mutual';
                    } elseif ($friendship->status === 'pending') {
                        if ((int)$friendship->requester_id === (int)$me->id) {
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
                    'id'                   => $partner?->id,
                    'first_name'           => $partner?->name ?? $partner?->email ?? 'Unknown',
                    'last_name'            => null,
                    'room_id'              => $room->id,
                    'last_message'         => $lastMsg?->content ?? '',
                    'last_message_at'      => optional($lastMsg?->created_at)->toIso8601String(),
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

    /*
    |--------------------------------------------------------------------------
    | Messages (FIXED)
    |--------------------------------------------------------------------------
    | ✅ One GET + one POST (no duplicates)
    | ✅ Same shape everywhere (GET/POST/notify)
    | ✅ POST will appear in route:list
    */

    // GET /api/chatMeetUp/messages/{room}
    Route::get('/chatMeetUp/messages/{room}', function (Request $request, $room) use ($shapeMessage) {
        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->first();

        if (!$chatRoom) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $messages = $chatRoom->messages()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn (Message $m) => $shapeMessage($m))
            ->values();

        return response()->json([
            'chat_room_id' => (int)$chatRoom->id,
            'messages'     => $messages,
        ], 200);
    });

    // POST /api/chatMeetUp/messages/{room}
    Route::post('/chatMeetUp/messages/{room}', function (Request $request, $room) use ($shapeMessage) {
        $data = $request->validate([
            'content' => ['nullable', 'string'],
            'kind'    => ['nullable', 'string'],
        ]);

        $me = $request->user();

        $chatRoom = ChatRoom::where('id', $room)
            ->whereHas('users', fn ($q) => $q->where('users.id', $me->id))
            ->firstOrFail();

        $message = $chatRoom->messages()->create([
            'user_id' => $me->id,
            'content' => $data['content'] ?? '',
            'kind'    => $data['kind'] ?? 'text',
        ]);

        $chatRoom->update(['last_message_at' => now()]);
        $chatRoom->loadMissing('users:id');

        // 1) ROOM broadcast (full)
        event(new ChatMessageCreated($chatRoom->id, $message));

        // 2) USER global notify (light but SAME SHAPE)
        foreach ($chatRoom->users as $u) {
            if ((int)$u->id === (int)$me->id) continue;

            event(new ChatGlobalNotify((int)$u->id, [
                'type'    => 'notify',
                'room_id' => (int)$chatRoom->id,
                'message' => $shapeMessage($message->fresh(['user:id,name,email'])),
            ]));
        }

        return response()->json([
            'type'    => 'message',
            'message' => $shapeMessage($message->fresh(['user:id,name,email'])),
        ], 201);
    });

    /*
    |--------------------------------------------------------------------------
    | Debug broadcast (optional)
    |--------------------------------------------------------------------------
    */
    Route::get('/debug/broadcast/{roomId}', function (Request $request, $roomId) {
        $roomId = (int)$roomId;
        $me = $request->user();

        $m = Message::create([
            'chat_room_id' => $roomId,
            'user_id'      => $me->id,
            'content'      => 'debug broadcast ' . now()->toDateTimeString(),
            'kind'         => defined(Message::class.'::KIND_TEXT') ? Message::KIND_TEXT : 'text',
        ]);

        event(new ChatMessageCreated($roomId, $m));

        return response()->json(['ok' => true, 'roomId' => $roomId, 'messageId' => $m->id]);
    });

});
