<?php

namespace App\Enums;

enum AnomalySeverity: string
{
    case INFO = 'INFO';
    case WARNING = 'WARNING';
    case CRITICAL = 'CRITICAL';

    public function rank(): int
    {
        return match ($this) {
            self::CRITICAL => 3,
            self::WARNING => 2,
            self::INFO => 1,
        };
    }
}
