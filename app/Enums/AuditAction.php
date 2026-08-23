<?php

namespace App\Enums;

enum AuditAction: string
{
    case CREATE = 'CREATE';
    case UPDATE = 'UPDATE';
    case DELETE = 'DELETE';
    case FINANCE_ENTITY_DELETED = 'FINANCE_ENTITY_DELETED';
    case ACTIVATE = 'ACTIVATE';
    case DEACTIVATE = 'DEACTIVATE';
    case SET_DEFAULT = 'SET_DEFAULT';
    case REVOKE = 'REVOKE';
    case REGENERATE = 'REGENERATE';
    case PAYMENT = 'PAYMENT';
    case TRANSFER = 'TRANSFER';
    case AI_CHAT_REQUESTED = 'AI_CHAT_REQUESTED';

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
