<?php

namespace App\Support;

use App\Models\InboxMessage;
use App\Models\User;

class Inbox
{
    public static function list(User $user, int $limit = 80)
    {
        return InboxMessage::query()
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')          // ✅ penting
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public static function unreadCount(User $user): int
    {
        return InboxMessage::query()
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')          // ✅ penting
            ->where('is_read', false)
            ->count();
    }

    public static function markRead(int $id, User $user): void
    {
        InboxMessage::query()
            ->where('id', $id)
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')          // ✅ penting
            ->update(['is_read' => true]);
    }

    public static function markAllRead(User $user): void
    {
        InboxMessage::query()
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')          // ✅ penting
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    // ✅ Clear all: soft delete + tandai read biar badge ikut turun
    public static function clearAll(User $user): void
    {
        InboxMessage::query()
            ->where('to_user_id', $user->id)
            ->whereNull('deleted_at')
            ->update([
                'deleted_at' => now(),
                'is_read'    => true,          // ✅ biar unreadCount jadi 0
                'updated_at' => now(),
            ]);
    }

    public static function toUser(int $toUserId, array $data): InboxMessage
    {
        return InboxMessage::create([
            'to_user_id'   => $toUserId,
            'from_user_id' => $data['from_user_id'] ?? null,
            'type'         => $data['type'] ?? 'info',
            'title'        => $data['title'] ?? '-',
            'message'      => $data['message'] ?? null,
            'url'          => $data['url'] ?? null,
            'is_read'      => false,
        ]);
    }

    public static function toRoles(array $roles, array $data, ?int $fromUserId = null): int
    {
        $userIds = \App\Models\User::whereIn('role', $roles)->pluck('id')->unique();

        $count = 0;
        foreach ($userIds as $uid) {
            self::toUser($uid, array_merge($data, [
                'from_user_id' => $fromUserId,
            ]));
            $count++;
        }
        return $count;
    }
}
