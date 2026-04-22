<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $supported = (array) config('app.supported_locales', []);

        if (in_array($locale, $supported, true)) {
            $request->session()->put('locale', $locale);
        }

        $fallback = route('public.home');

        return back(fallback: $fallback);
    }
}
