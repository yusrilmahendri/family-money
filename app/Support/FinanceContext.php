<?php

namespace App\Support;

/**
 * Compatibility constants for historical PRIBADI / USAHA_KEBUN values.
 *
 * The /apps session portal is retired. Ownership at runtime comes from
 * finance_entity_id. These constants remain for ownership backfill,
 * factories, and the context column that has not been dropped yet.
 *
 * Do not use session finance_context as an ownership source.
 */
class FinanceContext
{
    public const SESSION_KEY = 'finance_context';

    public const PRIBADI = 'PRIBADI';

    public const USAHA_KEBUN = 'USAHA_KEBUN';

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::PRIBADI => 'Keuangan Pribadi',
            self::USAHA_KEBUN => 'Keuangan Usaha Kebun',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::all());
    }

    public static function validationRule(): string
    {
        return 'required|in:'.implode(',', self::values());
    }

    public static function current(): ?string
    {
        $value = session(self::SESSION_KEY);

        return self::isValid($value) ? $value : null;
    }

    public static function currentOrDefault(string $default = self::PRIBADI): string
    {
        return self::current() ?? $default;
    }

    public static function set(string $context): void
    {
        if (self::isValid($context)) {
            session([self::SESSION_KEY => $context]);
        }
    }

    public static function isSelected(): bool
    {
        return self::current() !== null;
    }

    public static function isValid(?string $context): bool
    {
        return $context !== null && in_array($context, self::values(), true);
    }

    public static function label(?string $context = null): string
    {
        $context = $context ?? self::current();

        return self::all()[$context] ?? 'Belum dipilih';
    }
}
