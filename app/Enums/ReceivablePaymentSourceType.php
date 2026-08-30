<?php

namespace App\Enums;

enum ReceivablePaymentSourceType: string
{
    case MANUAL = 'MANUAL';
    case HARVEST_SALE_PAYMENT = 'HARVEST_SALE_PAYMENT';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
