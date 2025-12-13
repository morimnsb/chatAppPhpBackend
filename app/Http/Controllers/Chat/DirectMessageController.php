<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Events\ChatMessageCreated;

class DirectMessageController extends Controller
{
    /**
     * POST /api/chatMeetUp/make-contact
     */
    public function makeContact(Request $request)
    {
        // ✅ اعتبارسنجی ورودی
        $data = $request->validate([
            'recipientId'    => ['required', 'integer', 'exists:users,id'],
            'contentPreview' => ['nullable', 'string', 'max:5000'],
        ]);

        $authUser = $request->user(); // یا Auth::user()

        if (! $authUser) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $recipientId = (int) $data['recipientId'];
        $content     = $data['contentPreview'] ?? '';

        // ✅ جلوگیری از چت با خود
        if ($recipientId === $authUser->id) {
            return response()->json([
                'message' => "You cannot start a conversation with yourself.",
            ], 422);
        }

        // ✅ پیدا کردن یا ساختن روم دو نفره
        // این قسمت رو بر اساس ساختار مدل‌های خودت تنظیم کن
        $room = ChatRoom::firstOrCreateDirectRoom($authUser->id, $recipientId);

        // اگر متد static نداری، می‌تونی چیزی شبیه این بسازی:
        /*
        $room = ChatRoom::where('type', 'dm')
            ->whereHas('participants', function ($q) use ($authUser, $recipientId) {
                $q->whereIn('user_id', [$authUser->id, $recipientId]);
            })
            ->first();

        if (! $room) {
            $room = ChatRoom::create([
                'type' => 'dm',
                'name' => null,
            ]);

            $room->participants()->createMany([
                ['user_id' => $authUser->id],
                ['user_id' => $recipientId],
            ]);
        }
        */

        // ✅ ساخت پیام اول (یا پیام جدید)
        $message = Message::create([
            'room_id'      => $room->id,
            'sender_id'    => $authUser->id,
            'content'   => $request->input('content'),
            'read_receipt' => false,
        ]);

        // ✅ پخش realtime از طریق Reverb
        event(new ChatMessageCreated($message));

        // ✅ برگرداندن پاسخ به front
        // اگر Resource داری، می‌تونی از آن استفاده کنی
        return response()->json([
            'room'    => $room->toArray(),
            'message' => $message->toArray(),
        ], 200);
    }
}
