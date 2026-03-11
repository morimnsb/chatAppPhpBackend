<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Events\TypingIndicator;
use App\Models\User;
use App\Services\Chat\ChatFormatterService;
use App\Services\Chat\ChatFriendshipService;
use App\Services\Chat\ChatMessageService;
use App\Services\Chat\ChatRoomService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ChatMeetUpController extends Controller
{
    public function __construct(
        private readonly ChatFormatterService $formatter,
        private readonly ChatRoomService $roomService,
        private readonly ChatMessageService $messageService,
        private readonly ChatFriendshipService $friendshipService,
    ) {
    }

    public function rooms(Request $request): JsonResponse
    {
        return $this->chatrooms($request);
    }

    public function chatrooms(Request $request): JsonResponse
    {
        $me = $request->user();
        if (!$me instanceof User) return response()->json([], 401);

        return response()->json($this->roomService->listRoomsForUser((int) $me->id));
    }

    public function conversations(Request $request): JsonResponse
    {
        return $this->chatrooms($request);
    }

    public function createConvo(Request $request): JsonResponse
    {
        $me = $request->user();
        if (!$me instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        /** @var array{userId?:int|null,to_user_id?:int|null} $data */
        $data = $request->validate([
            'userId'     => ['nullable', 'integer'],
            'to_user_id' => ['nullable', 'integer'],
        ]);

        $otherId = (int) ($data['userId'] ?? $data['to_user_id'] ?? 0);

        if ($otherId === 0) {
            return response()->json(['message' => 'userId (or to_user_id) is required'], 422);
        }

        if ((int) $me->id === $otherId) {
            return response()->json(['message' => 'Cannot create conversation with yourself'], 422);
        }

        $other = $this->roomService->assertUserExists($otherId);
        if ($other === null) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $room = $this->roomService->createConversation((int) $me->id, $otherId);

        return response()->json([
            'ok'   => true,
            'room' => $room,
        ], 201);
    }

    public function getMessages(Request $request, int $room): JsonResponse
    {
        $me = $request->user();
        if (!$me instanceof User) return response()->json([], 401);

        $uid = (int) $me->id;
        $roomId = (int) $room;

        if (!$this->messageService->userIsMember($roomId, $uid)) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        return response()->json($this->messageService->listMessages($roomId), 200);
    }

    public function postMessage(Request $request, int $room): JsonResponse
    {
        /** @var array{content?:string|null,text?:string|null,kind?:string|null} $data */
        $data = $request->validate([
            'content' => ['nullable', 'string'],
            'text'    => ['nullable', 'string'],
            'kind'    => ['nullable', 'string'],
        ]);

        $me = $request->user();
        if (!$me instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $uid = (int) $me->id;
        $roomId = (int) $room;

        if (!$this->messageService->userIsMember($roomId, $uid)) {
            return response()->json(['message' => 'Room not found or you are not a member of this room.'], 404);
        }

        $content = trim((string) ($data['content'] ?? $data['text'] ?? ''));
        if ($content === '') {
            return response()->json(['message' => 'INVALID_TEXT'], 400);
        }

        $message = $this->messageService->createMessage(
            roomId: $roomId,
            userId: $uid,
            content: $content,
            kind: (string) ($data['kind'] ?? 'text'),
        );

        $fresh = $message->fresh(['user:id,name,email']);
        if (!$fresh instanceof \App\Models\Message) {
            return response()->json(['message' => 'Failed to refresh message'], 500);
        }

        return response()->json([
            'ok'      => true,
            'type'    => 'message',
            'room_id' => $roomId,
            'roomId'  => $roomId,
            'message' => $this->formatter->shapeMessage($fresh),
        ], 201);
    }

    public function sendFriendship(Request $request): JsonResponse
    {
        /** @var array{to_user_id:int,content?:string|null} $data */
        $data = $request->validate([
            'to_user_id' => ['required', 'integer'],
            'content'    => ['nullable', 'string'],
        ]);

        $from = $request->user();
        if (!$from instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $result = $this->friendshipService->sendFriendRequest(
                fromId: (int) $from->id,
                toId: (int) $data['to_user_id'],
                content: $data['content'] ?? null,
            );

            return response()->json($result['payload'], (int) $result['status']);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $first = collect($errors)->flatten()->first();

            return response()->json([
                'message' => is_string($first) ? $first : 'Validation error',
                'errors'  => $errors,
            ], 422);
        }
    }

    public function respondFriendship(Request $request): JsonResponse
    {
        $me = $request->user();
        if (!$me instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        /** @var array{friendship_id:int,action:'accept'|'reject'} $data */
        $data = $request->validate([
            'friendship_id' => ['required', 'integer'],
            'action'        => ['required', Rule::in(['accept', 'reject'])],
        ]);

        return response()->json(
            $this->friendshipService->respond(
                currentUserId: (int) $me->id,
                friendshipId: (int) $data['friendship_id'],
                action: (string) $data['action'],
            )
        );
    }

    public function typing(Request $request): JsonResponse
    {
        /** @var array{room_id:int,isTyping:bool} $validated */
        $validated = $request->validate([
            'room_id'  => 'required|integer',
            'isTyping' => 'required|boolean',
        ]);

        $me = $request->user();
        if (!$me instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $roomId   = (int) $validated['room_id'];
        $userId   = (int) $me->id;
        $isTyping = (bool) $validated['isTyping'];

        $event = new TypingIndicator($roomId, $userId, $isTyping);
        $event->dontBroadcastToCurrentUser();
        broadcast($event);

        return response()->json(['ok' => true]);
    }

    public function debugBroadcast(Request $request, int $roomId): JsonResponse
    {
        $me = $request->user();
        if (!$me instanceof User) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $m = $this->messageService->createMessage(
            roomId: $roomId,
            userId: (int) $me->id,
            content: 'debug broadcast ' . now()->toDateTimeString(),
            kind: 'text',
        );

        return response()->json([
            'ok'        => true,
            'roomId'    => $roomId,
            'messageId' => (int) $m->id,
        ]);
    }
}