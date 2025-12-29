<?php

namespace App\Http\Controllers;

use App\Events\ChatMessageCreated;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ChatMessageController extends Controller
{
    /**
     * POST /api/chat/rooms/{room}/messages
     * middleware: auth:sanctum
     *
     * Body:
     *  - content: string (required)
     */
    public function store(Request $request, ChatRoom $room): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        try {
            // ✅ Save message in DB
            $message = Message::create([
                'chat_room_id' => $room->id,
                'user_id'      => $user->id,
                'content'      => $validated['content'],
            ]);

            // ✅ Load user relation so broadcast payload has sender info
            $message->loadMissing('user:id,name,email');

            // ✅ Broadcast to other users in the room (sender won't get duplicate)
            broadcast(new ChatMessageCreated($room->id, $message))->toOthers();

            return response()->json([
                'ok'      => true,
                'room_id' => $room->id,
                'message' => [
                    'id'         => $message->id,
                    'room_id'    => $message->chat_room_id,
                    'sender_id'  => $message->user_id,
                    'sender'     => $message->user ? [
                        'id'    => $message->user->id,
                        'name'  => $message->user->name,
                        'email' => $message->user->email,
                    ] : null,
                    'content'    => $message->content,
                    'created_at' => optional($message->created_at)->toIso8601String(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('ChatMessageController@store failed', [
                'room_id' => $room->id ?? null,
                'user_id' => $user->id ?? null,
                'error'   => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to send message',
            ], 500);
        }
    }
}
