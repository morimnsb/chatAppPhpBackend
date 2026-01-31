<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatMeetUpController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/users', [AuthController::class, 'users']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('chatMeetUp')->group(function () {
        Route::post('/friendship', [ChatMeetUpController::class, 'sendFriendship']);
        Route::post('/friendship/respond', [ChatMeetUpController::class, 'respondFriendship']);

        Route::get('/chatrooms', [ChatMeetUpController::class, 'chatrooms']);
        Route::get('/conversations', [ChatMeetUpController::class, 'conversations']);

        Route::get('/messages/{room}', [ChatMeetUpController::class, 'getMessages']);
        Route::post('/messages/{room}', [ChatMeetUpController::class, 'postMessage']);
    });

    Route::get('/debug/broadcast/{roomId}', [ChatMeetUpController::class, 'debugBroadcast']);
});
