<?php declare(strict_types=1);

namespace App\Services\Chat;

use App\Events\ChatGlobalNotify;
use App\Events\ChatMessageCreated;
use App\Models\Friendship;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

final class ChatFriendshipService
{
    public function __construct(
        private readonly ChatRoomService $roomService,
        private readonly ChatFormatterService $formatter,
    ) {
    }

    /**
     * @return array{
     *   status:int,
     *   payload:array<string,mixed>
     * }
     */
    public function sendFriendRequest(int $fromId, int $toId, ?string $content): array
    {
        if ($fromId === $toId) {
            throw ValidationException::withMessages([
                'to_user_id' => ['نمی‌توانید خودتان را اضافه کنید.'],
            ]);
        }

        $to = User::find($toId);
        if ($to === null) {
            throw ValidationException::withMessages([
                'to_user_id' => ['User not found'],
            ]);
        }

        $existing = Friendship::query()
            ->where(function ($q) use ($fromId, $toId) {
                $q->where('from_user_id', $fromId)->where('to_user_id', $toId);
            })
            ->orWhere(function ($q) use ($fromId, $toId) {
                $q->where('from_user_id', $toId)->where('to_user_id', $fromId);
            })
            ->first();

        return DB::transaction(function () use ($fromId, $toId, $content, $existing): array {
            $room = $this->roomService->findOrCreateDmRoom($fromId, $toId);

            $text = trim((string) ($content ?? '')) ?: 'سلام! من برایت درخواست دوستی فرستادم 🙌';

            $dm = Message::create([
                'room_id' => (int) $room->id,
                'user_id' => $fromId,
                'content' => $text,
                'kind'    => 'friend_request',
            ]);

            $room->touch();

            $friendship = $existing;
            $status = 200;

            if ($existing === null) {
                $friendship = Friendship::create([
                    'from_user_id' => $fromId,
                    'to_user_id'   => $toId,
                    'status'       => 'pending',
                ]);
                $status = 201;
            }

            try {
                event(new ChatMessageCreated($dm));
            } catch (\Throwable $e) {
                Log::error('Broadcast failed (friend_request msg)', [
                    'msg_id' => (int) $dm->id,
                    'error'  => $e->getMessage(),
                ]);
            }

            foreach ([$toId, $fromId] as $notifyId) {
                try {
                    $fresh = $dm->fresh(['user:id,name,email']);
                    if (!$fresh instanceof Message) continue;

                    event(new ChatGlobalNotify($notifyId, [
                        'type'    => 'notify',
                        'room_id' => (int) $room->id,
                        'roomId'  => (int) $room->id,
                        'message' => $this->formatter->shapeMessage($fresh),
                    ]));
                } catch (\Throwable $e) {
                    Log::error('Broadcast failed (friend_request notify)', [
                        'room_id' => (int) $room->id,
                        'to_user' => $notifyId,
                        'error'   => $e->getMessage(),
                    ]);
                }
            }

            $membersGrouped = $this->roomService->membersForRoomIds([(int) $room->id]);
            $lastByRoom = $this->roomService->lastMessageByRoomIds([(int) $room->id]);

            $fresh2 = $dm->fresh(['user:id,name,email']);
            if (!$fresh2 instanceof Message) {
                throw ValidationException::withMessages([
                    'message' => ['Failed to refresh dm message'],
                ]);
            }

            return [
                'status' => $status,
                'payload' => [
                    'message'    => $existing ? 'درخواست دوستی قبلاً ثبت شده است.' : 'Friend request sent.',
                    'friendship' => $friendship,
                    'room'       => $this->formatter->shapeRoom($room, $membersGrouped, $lastByRoom, $fromId),
                    'dm_message' => $this->formatter->shapeMessage($fresh2),
                ],
            ];
        });
    }

    /**
     * @return array{
     *   message:string,
     *   friendship_id:int,
     *   friendship_raw:string
     * }
     */
    public function respond(int $currentUserId, int $friendshipId, string $action): array
    {
        $fs = Friendship::findOrFail($friendshipId);

        if ((int) $fs->to_user_id !== $currentUserId) {
            abort(403, 'Forbidden');
        }

        $fs->status = $action === 'accept' ? 'accepted' : 'rejected';
        $fs->save();

        return [
            'message'        => 'Friend request updated.',
            'friendship_id'  => (int) $fs->id,
            'friendship_raw' => (string) $fs->status,
        ];
    }
}