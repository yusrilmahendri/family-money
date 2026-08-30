<?php

namespace App\Enums;

enum ReceivableSourceType: string
{
    case MANUAL = 'MANUAL';
    case HARVEST_SALE = 'HARVEST_SALE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
