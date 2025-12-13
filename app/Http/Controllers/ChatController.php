<?php
// app/Http/Controllers/ChatController.php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class ChatController extends Controller
{
    



  // app/Http/Controllers/ChatController.php  (فقط هسته‌ی firstMessage)

public function firstMessage(Request $request)
{
    $data = $request->validate([
        'recipient_id' => ['required','integer','exists:users,id'],
        'content'      => ['nullable','string','max:5000'],
    ]);

    $authId = $request->user()->id;
    if ((int)$data['recipient_id'] === (int)$authId) {
        return response()->json(['message' => 'نمی‌توانید به خودتان DM بفرستید.'], 422);
    }

    $u1 = min($authId, (int)$data['recipient_id']);
    $u2 = max($authId, (int)$data['recipient_id']);
    $pkey = "u{$u1}_u{$u2}";

    $room = \App\Models\ChatRoom::query()->firstOrCreate(
        ['private_key' => $pkey],
        [
            'name'       => "DM {$u1}-{$u2}",   // 👈 مقدار به name بده تا NOT NULL گیر نده
            'is_private' => true,
        ]
    );

    // اتصال کاربران به پیوت (وجود نداشت، بساز)
    if (method_exists($room, 'users')) {
        $room->users()->syncWithoutDetaching([$authId, (int)$data['recipient_id']]);
    } else {
        // اگر رابطه تعریف نشده، دستی وارد کن
        \DB::table('chat_room_user')->insertOrIgnore([
            ['chat_room_id' => $room->id, 'user_id' => $authId],
            ['chat_room_id' => $room->id, 'user_id' => (int)$data['recipient_id']],
        ]);
    }

    $text = $data['content'] ?? 'Hi! Controller';
    $msg = \App\Models\Message::create([
        'chat_room_id' => $room->id,
        'user_id'      => $authId,
        'content'      => $text,   // 👈 مدل هر دو content/body را پر می‌کند
        'body'         => $text,   // 👈 در صورت NOT NULL بودن body خیال‌مان راحت است
    ]);

    return response()->json([
        'room'    => $room,
        'message' => $msg,
    ], 201);
}
// app/Http/Controllers/ChatController.php


public function listRoomMessages($roomId)
{
    // اگر ستون پیام شما "body" است، اینجا به "content" مپ می‌کنیم تا با فرانت هماهنگ شود
    $room = ChatRoom::with([
        'messages' => function ($q) {
            $q->orderBy('created_at', 'asc');
        },
        'messages.user' // برای اسم و عکس فرستنده
    ])->findOrFail($roomId);

    $out = $room->messages->map(function ($m) {
        return [
            'id'                 => $m->id,
            'chat_room_id'       => $m->chat_room_id,
            // فرانت انتظار دارد sender_id داشته باشد:
            'sender_id'          => $m->user_id,
            'sender_first_name'  => optional($m->user)->first_name,
            'photo'              => optional($m->user)->photo ?? null,
            // اگر در DB ستون پیام شما "body" است:
            'content'            => $m->body,     // <-- body → content
            'created_at'         => optional($m->created_at)->toISOString(),
            'timestamp'          => optional($m->created_at)->toISOString(), // برای هر دو فیلد
            'read_receipt'       => !is_null($m->read_at),
        ];
    })->values();

    return response()->json($out, 200);
}


public function postMessage(Request $request, $roomId)
{
    $user = $request->user(); // sanctum
    $data = $request->validate([
        'content' => ['required','string','max:5000'],  // فرانت content می‌فرستد
    ]);

    // اتاق را پیدا کن
    $room = ChatRoom::findOrFail((int)$roomId);

    // اگر پیوت وجود ندارد، کاربر را عضو کن (برای اطمینان)
    if (!$room->users()->where('users.id', $user->id)->exists()) {
        $room->users()->attach($user->id);
    }

    // مپ content → body
    $msg = null;
    DB::transaction(function () use (&$msg, $room, $user, $data) {
        $msg = Message::create([
            'chat_room_id' => $room->id,
            'user_id'      => $user->id,
            'body'         => $data['content'], // <-- ستون واقعی DB
        ]);
    });

    // پاسخ طبق چیزی که فرانت انتظار دارد
    return response()->json([
        'id'                 => $msg->id,
        'chat_room_id'       => $room->id,
        'sender_id'          => $user->id,
        'sender_first_name'  => $user->first_name ?? null,
        'photo'              => $user->photo ?? null,
        'content'            => $msg->body, // back to front shape
        'created_at'         => $msg->created_at?->toISOString(),
        'timestamp'          => $msg->created_at?->toISOString(),
        'read_receipt'       => false,
    ], 201);
}


}
