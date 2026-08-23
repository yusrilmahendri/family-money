<?php

namespace App\Enums;

use Carbon\CarbonInterface;

enum ReceivableStatus: string
{
    case OPEN = 'OPEN';
    case PARTIALLY_PAID = 'PARTIALLY_PAID';
    case PAID = 'PAID';
    case OVERDUE = 'OVERDUE';

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
            self::OPEN => 'Open',
            self::PARTIALLY_PAID => 'Dibayar sebagian',
            self::PAID => 'Lunas',
            self::OVERDUE => 'Jatuh tempo',
        };
    }

    public static function fromState(
        float $principal,
        float $remaining,
        mixed $dueDate = null,
        ?CarbonInterface $asOf = null
    ): self {
        if ($remaining <= 0) {
            return self::PAID;
        }

        $today = ($asOf ?? now())->toDateString();
        $due = $dueDate instanceof CarbonInterface
            ? $dueDate->toDateString()
            : (filled($dueDate) ? (string) $dueDate : null);

        if ($due !== null && $due < $today) {
            return self::OVERDUE;
        }

        if ($remaining < $principal) {
            return self::PARTIALLY_PAID;
        }

        return self::OPEN;
    }
}
