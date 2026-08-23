<?php

namespace App\Enums;

enum FinanceEntityType: string
{
    case FAMILY = 'FAMILY';
    case BUSINESS = 'BUSINESS';

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
