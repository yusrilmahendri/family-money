<?php

namespace App\Enums;

enum FinanceAccountType: string
{
    case CASH = 'CASH';
    case BANK = 'BANK';
    case EWALLET = 'EWALLET';
    case OTHER = 'OTHER';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::CASH => 'Kas',
            self::BANK => 'Bank',
            self::EWALLET => 'E-Wallet',
            self::OTHER => 'Lainnya',
        };
    }
}
