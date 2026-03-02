<?php

namespace App\Support\Notifications;

class NotificationRecipientDefaults
{
    /**
     * @return array{notify_waiting:bool,notify_approved:bool,notify_rejected:bool}
     */
    public static function flagsForRole(string $role): array
    {
        $normalized = self::normalizeRole($role);

        return match ($normalized) {
            'director' => [
                'notify_waiting' => true,
                'notify_approved' => false,
                'notify_rejected' => false,
            ],
            'admin', 'operator' => [
                'notify_waiting' => false,
                'notify_approved' => true,
                'notify_rejected' => true,
            ],
            default => [
                'notify_waiting' => true,
                'notify_approved' => true,
                'notify_rejected' => true,
            ],
        };
    }

    public static function normalizeRole(string $role): string
    {
        return strtolower(trim($role));
    }
}

