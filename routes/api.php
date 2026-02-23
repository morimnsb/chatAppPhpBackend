<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatMeetUpController;


// ✅ broadcasting auth endpoint should be JWT protected (your custom middleware)
Broadcast::routes([
    'middleware' => ['node.jwt'],
]);
/*
|--------------------------------------------------------------------------
| AUTH ROUTES
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-verify', [AuthController::class, 'resendVerifyCode']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::middleware('node.jwt')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/users', [AuthController::class, 'users']);
    });
});

/*
|--------------------------------------------------------------------------
| CHAT ROUTES
|--------------------------------------------------------------------------
*/
Route::middleware('node.jwt')->prefix('chat')->group(function () {
    Route::get('/rooms', [ChatMeetUpController::class, 'rooms']);
    Route::get('/conversations', [ChatMeetUpController::class, 'conversations']);
    Route::post('/conversations', [ChatMeetUpController::class, 'createConvo']);

    Route::get('/messages/{room}', [ChatMeetUpController::class, 'getMessages']);
    Route::post('/messages/{room}', [ChatMeetUpController::class, 'postMessage']);

    Route::post('/friendship', [ChatMeetUpController::class, 'sendFriendship']);
    Route::post('/typing', [ChatMeetUpController::class, 'typing']);
});

Route::get('/health', fn () => response()->json(['ok' => true]));