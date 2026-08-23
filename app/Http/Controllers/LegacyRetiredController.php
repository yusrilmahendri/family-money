<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

class LegacyRetiredController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()
            ->route('home')
            ->with('danger', 'Portal konteks /apps sudah diganti tautan privat. Gunakan /access/{token} atau /e/{entity}.');
    }
}
