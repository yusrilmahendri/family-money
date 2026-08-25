<?php

it('exposes rupiah helpers after the application boots', function () {
    expect(function_exists('rupiah'))->toBeTrue()
        ->and(function_exists('rupiah_input'))->toBeTrue()
        ->and(rupiah(1_000_000))->toBe('Rp 1.000.000')
        ->and(rupiah(0))->toBe('Rp 0')
        ->and(rupiah(-1_000_000))->toBe('-Rp 1.000.000')
        ->and(rupiah(null))->toBe('Rp 0')
        ->and(rupiah('22500000.00'))->toBe('Rp 22.500.000')
        ->and(rupiah_input(''))->toBe('')
        ->and(rupiah_input(null))->toBe('')
        ->and(rupiah_input(0))->toBe('Rp 0')
        ->and(rupiah_input(22_500_000))->toBe('Rp 22.500.000');
});
