<?php declare(strict_types=1);

namespace App\Services\Chat;

use App\Models\ChatRoom;
use App\Models\ChatRoomMember;
use App\Models\Message;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

final class ChatFormatterService
{
    private function asString(mixed $v, string $default = ''): string
    {
        if (is_string($v)) return $v;
        if (is_int($v) || is_float($v) || is_bool($v)) return (string) $v;
        return $default;
    }

    private function iso(mixed $dt): ?string
    {
        return $dt instanceof CarbonInterface ? $dt->toIso8601String() : null;
    }

    private function toInt(mixed $v, int $default = 0): int
    {
        if (is_int($v)) return $v;
        if (is_bool($v)) return $v ? 1 : 0;
        if (is_string($v) || is_float($v)) return is_numeric((string) $v) ? (int) $v : $default;
        return $default;
    }

    /** @return array{id:int,name:string,email:string}|null */
    public function shapeUser(mixed $u): ?array
    {
        if (!$u instanceof User) return null;

        return [
            'id'    => (int) $u->id,
            'name'  => $this->asString($u->name),
            'email' => $this->asString($u->email),
        ];
    }

    /** @return array<string,mixed> */
    public function shapeMessage(Message $m): array
    {
        $m->loadMissing('user:id,name,email');

        $roomId = (int) $m->room_id;
        $userId = (int) $m->user_id;

        return [
            'id' => (int) $m->id,

            // canonical
            'roomId'    => $roomId,
            'userId'    => $userId,
            'content'   => $m->content ?? null,
            'kind'      => $m->kind ?? null,
            'createdAt' => $this->iso($m->created_at),
            'updatedAt' => $this->iso($m->updated_at),

            // aliases
            'room_id'    => $roomId,
            'user_id'    => $userId,
            'sender_id'  => $userId,
            'text'       => $m->content ?? null,
            'created_at' => $this->iso($m->created_at),
            'updated_at' => $this->iso($m->updated_at),

            'user' => $this->shapeUser($m->user),
        ];
    }

    /**
     * @param \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChatRoomMember>> $membersGrouped
     * @param array<int, \App\Models\Message|null> $lastByRoom
     * @return array<string,mixed>
     */
    public function shapeRoom(ChatRoom $r, Collection $membersGrouped, array $lastByRoom, int $meId): array
    {
        /** @var EloquentCollection<int, ChatRoomMember> $rawMembers */
        $rawMembers = $membersGrouped->get((int) $r->id, new EloquentCollection());

        /** @var Collection<int, array<string,mixed>> $members */
        $members = $rawMembers->map(function (ChatRoomMember $mm): array {
            $roomId = (int) $mm->room_id;
            $userId = (int) $mm->user_id;

            return [
                'id'        => (int) $mm->id,
                'roomId'    => $roomId,
                'userId'    => $userId,
                'createdAt' => null,

                'room_id'    => $roomId,
                'user_id'    => $userId,
                'created_at' => null,

                'user' => $this->shapeUser($mm->user),
            ];
        })->values();

        $last = $lastByRoom[(int) $r->id] ?? null;

        /** @var array{id:int,name:string,email:string}|null $partnerUser */
        $partnerUser = null;

        if (!(bool) ($r->is_group ?? false)) {
            /** @var array{id:int,name:string,email:string}|null $found */
            $found = null;

            foreach ($members as $mm) {
                $u = $mm['user'] ?? null;
                if (!is_array($u)) continue;

                $id = isset($u['id']) ? $this->toInt($u['id'], 0) : 0;
                if ($id !== 0 && $id !== $meId) {
                    $found = [
                        'id'    => $id,
                        'name'  => $this->asString($u['name'] ?? ''),
                        'email' => $this->asString($u['email'] ?? ''),
                    ];
                    break;
                }
            }

            $partnerUser = $found;
        }

        $title = null;
        if ((bool) ($r->is_group ?? false)) {
            $title = $r->name ?? null;
        } elseif ($partnerUser !== null) {
            $title = $partnerUser['name'] !== '' ? $partnerUser['name'] : $partnerUser['email'];
        }

        $kind = (bool) ($r->is_group ?? false) ? 'group' : 'dm';

        return [
            'id'         => (int) $r->id,
            'name'       => $r->name,
            'title'      => $title,
            'kind'       => $kind,
            'isGroup'    => (bool) ($r->is_group ?? false),
            'privateKey' => $r->private_key,
            'createdAt'  => $this->iso($r->created_at),
            'updatedAt'  => $this->iso($r->updated_at),
            'members'    => $members,

            'lastMessage' => $last instanceof Message ? $this->shapeMessage($last) : null,

            'is_private' => $kind === 'dm',
            'is_group'   => $kind === 'group',
            'partnerId'  => $partnerUser['id'] ?? null,
            'partner_id' => $partnerUser['id'] ?? null,

            'last_message' => $last instanceof Message ? [
                'id'         => (int) $last->id,
                'room_id'    => (int) $last->room_id,
                'content'    => $last->content ?? null,
                'text'       => $last->content ?? null,
                'kind'       => $last->kind ?? 'text',
                'user_id'    => (int) $last->user_id,
                'created_at' => $this->iso($last->created_at),
                'user'       => $this->shapeUser($last->user),
            ] : null,

            'last_message_text' => $last instanceof Message ? ($last->content ?? '') : '',
            'last_message_at'   => $last instanceof Message ? $this->iso($last->created_at) : null,
            'updated_at'        => $this->iso($r->updated_at),

            'unread_count' => 0,
        ];
    }
}