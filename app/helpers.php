<?php

use App\Support\Rupiah;

if (! function_exists('rupiah')) {
    function rupiah(mixed $amount): string
    {
        return Rupiah::format($amount);
    }
}

if (! function_exists('rupiah_input')) {
    function rupiah_input(mixed $amount): string
    {
        return Rupiah::formatInput($amount);
    }
}
