<?php

use App\Support\Rupiah;

it('parses formatted and raw rupiah values into digit strings', function () {
    expect(Rupiah::parse('Rp 1.000.000'))->toBe('1000000')
        ->and(Rupiah::parse('1.000.000'))->toBe('1000000')
        ->and(Rupiah::parse('1000000'))->toBe('1000000')
        ->and(Rupiah::parse('Rp1.000.000'))->toBe('1000000')
        ->and(Rupiah::parse('Rp 22.500.000'))->toBe('22500000')
        ->and(Rupiah::parse('22500000.00'))->toBe('22500000')
        ->and(Rupiah::parse('22.500.000,00'))->toBe('22500000');
});

it('formats display values with a space after Rp', function () {
    expect(Rupiah::format(1_000_000))->toBe('Rp 1.000.000')
        ->and(Rupiah::format(22_500_000))->toBe('Rp 22.500.000')
        ->and(Rupiah::format(200_000_000))->toBe('Rp 200.000.000')
        ->and(Rupiah::format(0))->toBe('Rp 0')
        ->and(Rupiah::format(-1_000_000))->toBe('-Rp 1.000.000')
        ->and(Rupiah::format(null))->toBe('Rp 0')
        ->and(Rupiah::format('22500000.00'))->toBe('Rp 22.500.000')
        ->and(rupiah(22_500_000))->toBe('Rp 22.500.000');
});

it('keeps empty money inputs empty and formats existing numeric values', function () {
    expect(Rupiah::formatInput(''))->toBe('')
        ->and(Rupiah::formatInput(null))->toBe('')
        ->and(Rupiah::formatInput(0))->toBe('Rp 0')
        ->and(rupiah_input('22500000.00'))->toBe('Rp 22.500.000');
});

it('rejects non-positive amounts through reusable validation rules', function () {
    $rules = Rupiah::positiveRules();

    expect($rules[0])->toBe('required')
        ->and($rules[1])->toBe('string')
        ->and(Rupiah::toFloat('abc'))->toBe(0.0)
        ->and(Rupiah::toFloat('Rp 0'))->toBe(0.0);
});
