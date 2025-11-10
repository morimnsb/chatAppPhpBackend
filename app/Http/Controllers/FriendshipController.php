<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;               // ⬅️ اضافه شد
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use App\Models\Friendship;
use App\Models\User;

class FriendshipController extends Controller
{
    public function store(Request $request): JsonResponse   // ⬅️ FormRequest حذف شد
    {
        // ولیدیشن ساده اینجا
        $data = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $fromUserId = Auth::id();
        if (!$fromUserId) {
            return response()->json(['error' => 'Invalid or missing token'], 401);
        }

        $toUserId = (int) $data['to_user_id'];
        if ($fromUserId === $toUserId) {
            return response()->json(['error' => 'Users must be different.'], 400);
        }

        $fromUser = User::find($fromUserId);
        $toUser   = User::find($toUserId);
        if (!$fromUser || !$toUser) {
            return response()->json(['error' => 'One or both users do not exist.'], 404);
        }

        $low  = min($fromUserId, $toUserId);
        $high = max($fromUserId, $toUserId);

        if (Friendship::where('user_low_id', $low)->where('user_high_id', $high)->exists()) {
            return response()->json(['message' => 'Friendship already exists.'], 200);
        }

        try {
            DB::transaction(function () use ($fromUserId, $toUserId, $low, $high) {
                Friendship::create([
                    'from_user_id' => $fromUserId,
                    'to_user_id'   => $toUserId,
                    'user_low_id'  => $low,
                    'user_high_id' => $high,
                ]);
            });
        } catch (QueryException $e) {
            return response()->json(['message' => 'Friendship already exists.'], 200);
        } catch (\Throwable $e) {
            \Log::error('Friendship error', ['err' => $e->getMessage()]);
            return response()->json(['error' => 'An unexpected error occurred while creating friendship.'], 500);
        }

        return response()->json(['message' => 'Friendship created successfully.'], 200);
    }
}
