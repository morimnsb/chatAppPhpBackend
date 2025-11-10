<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function send(Request $request, $roomId)
    {
        return response()->json([
            'status' => 'ok',
            'roomId' => $roomId,
            'body'   => $request->input('body')
        ]);
    }
}
