<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class ChatRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // TODO: در صورت نیاز، اتاق‌های مرتبط با کاربر لاگین‌شده:
        // $userId = $request->user()->id ?? null;
        // ->whereHas('members', fn($q) => $q->where('user_id', $userId))

        $rooms = ChatRoom::query()
            ->orderByDesc('updated_at')
            ->get(['id', 'name', 'description', 'last_message_at', 'updated_at']);

        $payload = $rooms->map(function ($r) {
            return [
                'id'              => $r->id,
                'name'            => (string) ($r->name ?? ''),
                'description'     => $r->description, // nullable
                'last_message_at' => $this->toIso8601OrNull($r->getAttribute('last_message_at')),
                'updated_at'      => $this->toIso8601OrNull($r->getAttribute('updated_at')),
            ];
        })->values();

        return response()->json($payload, 200);
    }

    /**
     * مقدار زمان را (Carbon|DateTime|string|null) به ISO-8601 تبدیل می‌کند.
     * اگر null یا فرمت نامعتبر باشد، null برمی‌گرداند.
     */
    private function toIso8601OrNull($value): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->toIso8601String();
        }

        // بعضی مدل‌ها timestamps را به string برمی‌گردانند
        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
