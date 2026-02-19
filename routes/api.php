<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatMeetUpController;

/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']); // 🔥 جدید

    Route::post('/resend-verify', [AuthController::class, 'resendVerifyCode']); // 🔥 جدید

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/users', [AuthController::class, 'users']);
    });
});


/*
|--------------------------------------------------------------------------
| CHAT ROUTES (UPDATED TO MATCH NODE BACKEND)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('chat')->group(function () {

    Route::get('/rooms', [ChatMeetUpController::class, 'rooms']);
    Route::get('/conversations', [ChatMeetUpController::class, 'conversations']);
    Route::post('/conversations', [ChatMeetUpController::class, 'createConvo']);

    Route::get('/messages/{room}', [ChatMeetUpController::class, 'getMessages']);
    Route::post('/messages/{room}', [ChatMeetUpController::class, 'postMessage']);

    Route::post('/friendship', [ChatMeetUpController::class, 'sendFriendship']);

    // ✅ FIX: this becomes /api/chat/typing
    Route::post('/typing', [ChatMeetUpController::class, 'typing']);
});


/*
|--------------------------------------------------------------------------
| DEBUG
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->get(
    '/debug/broadcast/{roomId}',
    [ChatMeetUpController::class, 'debugBroadcast']
);
Route::get('/health', fn () => response()->json(['ok' => true]));
