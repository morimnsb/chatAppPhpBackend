<?php
// app/Http/Controllers/ChatBroadcastController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\ChatMessageCreated;
use App\Events\TypingIndicator;

class ChatBroadcastController extends Controller
{
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'room_id' => 'required|integer',
            'text'    => 'required|string|max:2000',
        ]);

        $payload = [
            'sender_id' => $request->user()->id,
            'text'      => $validated['text'],
            'ts'        => now()->toISOString(),
        ];

        broadcast(new ChatMessageCreated($validated['room_id'], $payload))->toOthers();

        return response()->json(['ok' => true, 'sent' => $payload]);
    }

    public function typing(Request $request)
    {
        $validated = $request->validate([
            'room_id'   => 'required|integer',
            'isTyping'  => 'required|boolean',
        ]);

        $payload = [
            'sender_id' => $request->user()->id,
            'isTyping'  => $validated['isTyping'],
            'ts'        => now()->toISOString(),
        ];

        broadcast(new TypingIndicator($validated['room_id'], $payload))->toOthers();

        return response()->json(['ok' => true]);
    }
}
