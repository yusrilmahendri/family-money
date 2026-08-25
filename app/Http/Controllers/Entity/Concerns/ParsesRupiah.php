<?php

namespace App\Http\Controllers\Entity\Concerns;

use App\Support\Rupiah;

trait ParsesRupiah
{
    protected function parseRupiah(?string $raw): float
    {
        return Rupiah::toFloat($raw);
    }

    /**
     * @return list<mixed>
     */
    protected function positiveRupiahRules(): array
    {
        return Rupiah::positiveRules();
    }
}
