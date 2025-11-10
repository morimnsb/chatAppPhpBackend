<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Http\Request;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChatRoomController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\FriendshipController;
use App\Http\Controllers\TestBroadcastController;

/*
|--------------------------------------------------------------------------
| Auth (Public)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register',      [AuthController::class, 'register']);
    Route::post('/login',         [AuthController::class, 'login']);
    Route::post('/verify-email',  [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp',    [AuthController::class, 'resendOtp']);
});

/*
|--------------------------------------------------------------------------
| Protected (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('auth/me',    [AuthController::class, 'me']);
    Route::get('auth/users', [UsersController::class, 'index']);

    Route::prefix('chatMeetUp')->group(function () {
        Route::get('chatrooms',         [ChatRoomController::class, 'index'])->name('chat.rooms.index');
        Route::get('conversations',     [ConversationController::class, 'index'])->name('chat.conversations.index');

        Route::post('friendship',       [FriendshipController::class, 'store'])->name('chat.friendship.request');
        Route::get('messages/{roomId}', [ChatController::class, 'listRoomMessages'])->whereNumber('roomId')->name('chat.room.messages.list');
        Route::post('messages/{roomId}',[ChatController::class, 'postMessage'])->whereNumber('roomId')->name('chat.room.messages.post');
        Route::post('first-message', [\App\Http\Controllers\ChatController::class, 'firstMessage'])
        ->name('chat.firstMessage');
        Route::post('read-receipt/{roomId}', [ChatController::class, 'setReadReceipt'])->whereNumber('roomId')->name('chat.room.read-receipt');
    });

    // legacy aliases (اختیاری)
    Route::get('rooms',         [ChatRoomController::class, 'index'])->name('api.rooms');
    Route::get('conversations', [ConversationController::class, 'index'])->name('api.conversations');

    Route::middleware('throttle:chat-actions')->group(function () {
        Route::post('chat/messages', [ChatController::class, 'send'])->name('chat.send');
        Route::post('chat/typing',   [ChatController::class, 'typing'])->name('chat.typing');
        Route::post('chat/read',     [ChatController::class, 'readReceipt'])->name('chat.read');
    });

    Route::post('rooms/{roomId}/send', [TestBroadcastController::class, 'sendToRoom'])
        ->whereNumber('roomId')
        ->name('chat.rooms.test-send');
});

/*
|--------------------------------------------------------------------------
| Broadcasting Auth (Echo / Reverb)
|--------------------------------------------------------------------------
*/
Route::post('broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware(['auth:sanctum']);
