<?php

namespace App\Enums;

enum ReceivablePaymentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case REVERSED = 'REVERSED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
