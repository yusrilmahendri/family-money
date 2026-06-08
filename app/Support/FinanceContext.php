<?php

namespace App\Support;

/**
 * Helper konteks "aplikasi" keuangan aktif.
 *
 * Memisahkan keuangan PRIBADI dan USAHA_KEBUN tanpa login/middleware.
 * Konteks aktif disimpan di session dengan key `finance_context`.
 *
 * Catatan: SALDO tetap global/shared, tidak terpisah per konteks.
 */
class FinanceContext
{
    public const SESSION_KEY = 'finance_context';

    public const PRIBADI = 'PRIBADI';
    public const USAHA_KEBUN = 'USAHA_KEBUN';

    /**
     * Semua konteks valid beserta labelnya.
     */
    public static function all(): array
    {
        return [
            self::PRIBADI => 'Keuangan Pribadi',
            self::USAHA_KEBUN => 'Keuangan Usaha Kebun',
        ];
    }

    /**
     * Daftar nilai valid (untuk validasi `in:...`).
     */
    public static function values(): array
    {
        return array_keys(self::all());
    }

    /**
     * Aturan validasi untuk request.
     */
    public static function validationRule(): string
    {
        return 'required|in:'.implode(',', self::values());
    }

    /**
     * Konteks aktif dari session (null jika belum dipilih).
     */
    public static function current(): ?string
    {
        $value = session(self::SESSION_KEY);

        return self::isValid($value) ? $value : null;
    }

    /**
     * Konteks aktif dengan default fallback (untuk filter agar tidak null).
     */
    public static function currentOrDefault(string $default = self::PRIBADI): string
    {
        return self::current() ?? $default;
    }

    /**
     * Set konteks aktif ke session.
     */
    public static function set(string $context): void
    {
        if (self::isValid($context)) {
            session([self::SESSION_KEY => $context]);
        }
    }

    /**
     * Apakah konteks sudah dipilih?
     */
    public static function isSelected(): bool
    {
        return self::current() !== null;
    }

    public static function isValid(?string $context): bool
    {
        return $context !== null && in_array($context, self::values(), true);
    }

    /**
     * Label manusiawi untuk konteks tertentu (atau konteks aktif).
     */
    public static function label(?string $context = null): string
    {
        $context = $context ?? self::current();

        return self::all()[$context] ?? 'Belum dipilih';
    }
}
