<?php

namespace App\Enums;

enum AuditActorType: string
{
    case ADMIN = 'ADMIN';
    case PRIVATE_LINK = 'PRIVATE_LINK';
    case SYSTEM = 'SYSTEM';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(?string $value): bool
    {
        return $value !== null && self::tryFrom($value) !== null;
    }
}
