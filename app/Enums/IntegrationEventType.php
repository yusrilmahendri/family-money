<?php

namespace App\Enums;

enum IntegrationEventType: string
{
    case PLANTATION_PURCHASE_POSTED = 'PLANTATION_PURCHASE_POSTED';
    case PLANTATION_PURCHASE_CANCELLED = 'PLANTATION_PURCHASE_CANCELLED';
    case PLANTATION_PAYROLL_PAID = 'PLANTATION_PAYROLL_PAID';
    case HARVEST_SALE_POSTED = 'HARVEST_SALE_POSTED';
    case HARVEST_SALE_CANCELLED = 'HARVEST_SALE_CANCELLED';
    case HARVEST_SALE_PAYMENT_RECEIVED = 'HARVEST_SALE_PAYMENT_RECEIVED';
    case HARVEST_SALE_PAYMENT_REVERSED = 'HARVEST_SALE_PAYMENT_REVERSED';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function supportedVersion(): int
    {
        return 1;
    }
}
