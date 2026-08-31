<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LegacyRetiredController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route('home');
    }
}
