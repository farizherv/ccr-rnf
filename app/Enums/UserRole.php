<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin    = 'admin';
    case Director = 'director';
    case Operator = 'operator';

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin    => 'Admin',
            self::Director => 'Direktur',
            self::Operator => 'Operator',
        };
    }

    /**
     * All values as a flat array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
