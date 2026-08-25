<?php

namespace App\Support;

final class Rupiah
{
    public static function parse(mixed $raw): string
    {
        if ($raw === null) {
            return '0';
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return '0';
        }

        $negative = str_starts_with(ltrim($value), '-');
        $value = preg_replace('/^rp\s*/i', '', ltrim($value, " \t-")) ?? '';
        $value = str_replace(["\u{00A0}", ' '], '', $value);

        if (preg_match('/^\d+[.,]\d{1,2}$/', $value) === 1) {
            $value = preg_replace('/[.,]\d{1,2}$/', '', $value) ?? $value;
        } elseif (preg_match('/,\d{1,2}$/', $value) === 1) {
            $value = preg_replace('/,\d{1,2}$/', '', $value) ?? $value;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';
        if ($digits === '') {
            return '0';
        }

        $digits = ltrim($digits, '0');
        $digits = $digits === '' ? '0' : $digits;

        return $negative && $digits !== '0' ? '-'.$digits : $digits;
    }

    public static function toFloat(mixed $raw): float
    {
        return (float) self::parse($raw);
    }

    public static function format(mixed $amount): string
    {
        $number = is_numeric($amount) ? (float) $amount : self::toFloat($amount);
        $formatted = number_format(abs($number), 0, ',', '.');

        return ($number < 0 ? '-' : '').'Rp '.$formatted;
    }

    public static function formatInput(mixed $amount): string
    {
        if ($amount === null) {
            return '';
        }

        if (is_string($amount) && trim($amount) === '') {
            return '';
        }

        return self::format($amount);
    }

    /**
     * @return list<mixed>
     */
    public static function positiveRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (self::toFloat($value) <= 0) {
                    $fail('Jumlah harus lebih dari 0.');
                }
            },
        ];
    }
}
