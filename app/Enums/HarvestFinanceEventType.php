<?php

namespace App\Enums;

enum HarvestFinanceEventType: string
{
    case HARVEST_SALE_POSTED = 'HARVEST_SALE_POSTED';
    case HARVEST_SALE_PAYMENT_RECEIVED = 'HARVEST_SALE_PAYMENT_RECEIVED';
    case HARVEST_SALE_PAYMENT_REVERSED = 'HARVEST_SALE_PAYMENT_REVERSED';
    case HARVEST_SALE_CANCELLED = 'HARVEST_SALE_CANCELLED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
