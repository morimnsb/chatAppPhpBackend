<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Events\ChatMessageCreated;

class TestBroadcastController extends Controller
{
    public function sendToRoom(Request $request, int $roomId)
    {
        $text = $request->input('text', 'Hello from server!');
        $senderId = (int) ($request->user()->id ?? 0);

        event(new ChatMessageCreated($roomId, [
            'text'      => $text,
            'sender_id' => $senderId,
            'sent_at'   => now()->toISOString(),
        ]));

        return response()->json(['ok' => true]);
    }
}
