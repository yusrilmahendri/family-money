<?php

namespace App\Http\Controllers\Entity\Concerns;

trait ParsesRupiah
{
    protected function parseRupiah(?string $raw): float
    {
        $digits = preg_replace('/\D/', '', (string) $raw);

        return (float) ($digits === '' ? 0 : $digits);
    }

    /**
     * @return list<mixed>
     */
    protected function positiveRupiahRules(): array
    {
        return [
            'required',
            'string',
            function (string $attribute, mixed $value, \Closure $fail): void {
                $digits = preg_replace('/\D/', '', (string) $value);

                if ($digits === '' || (float) $digits <= 0) {
                    $fail('Jumlah harus lebih dari 0.');
                }
            },
        ];
    }
}
