<?php

namespace App\Services;

use App\Support\FinanceContext;
use Illuminate\Http\RedirectResponse;

/**
 * Service konteks keuangan aktif (PRIBADI / USAHA_KEBUN).
 *
 * Bekerja di atas session via App\Support\FinanceContext.
 * Menyediakan helper konteks + guard akses fitur tanpa middleware.
 */
class FinanceContextService
{
    /**
     * Konteks aktif. Null jika belum dipilih (controller harus redirect ke /apps).
     */
    public function current(): ?string
    {
        return FinanceContext::current();
    }

    public function isSelected(): bool
    {
        return FinanceContext::isSelected();
    }

    public function isPersonal(): bool
    {
        return FinanceContext::current() === FinanceContext::PRIBADI;
    }

    public function isFarm(): bool
    {
        return FinanceContext::current() === FinanceContext::USAHA_KEBUN;
    }

    public function label(?string $context = null): string
    {
        return FinanceContext::label($context);
    }

    /**
     * Konfigurasi fitur konteks aktif (atau konteks tertentu) dari config/finance.php.
     */
    public function config(?string $context = null): array
    {
        $context = $context ?? FinanceContext::currentOrDefault();

        return config('finance.contexts.'.$context, []);
    }

    /**
     * Daftar menu yang boleh tampil pada konteks aktif.
     */
    public function menu(?string $context = null): array
    {
        return $this->config($context)['menu'] ?? [];
    }

    /**
     * Apakah sebuah key menu boleh tampil pada konteks aktif?
     */
    public function menuAllowed(string $key, ?string $context = null): bool
    {
        return in_array($key, $this->menu($context), true);
    }

    /* ===================== GUARD AKSES (tanpa middleware) ===================== */

    /**
     * Guard fitur khusus PRIBADI.
     * Return RedirectResponse jika TIDAK boleh, atau null jika boleh lanjut.
     */
    public static function guardPersonal(): ?RedirectResponse
    {
        return self::guard(FinanceContext::PRIBADI, 'Fitur ini hanya untuk Keuangan Pribadi.');
    }

    /**
     * Guard fitur khusus USAHA_KEBUN.
     */
    public static function guardFarm(): ?RedirectResponse
    {
        return self::guard(FinanceContext::USAHA_KEBUN, 'Fitur ini hanya untuk Keuangan Usaha Kebun.');
    }

    /**
     * Guard generik: pastikan konteks aktif sama dengan $required.
     */
    protected static function guard(string $required, string $message): ?RedirectResponse
    {
        $current = FinanceContext::current();

        if ($current === null) {
            return redirect()->route('apps.index');
        }

        if ($current !== $required) {
            return redirect()->route('dashboard')->with('danger', $message);
        }

        return null;
    }
}
