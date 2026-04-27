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

            // Persist the choice on the user so queued mailables (which run
            // outside the request lifecycle) and future sessions render in
            // the right language. Guests stay on session-only.
            $user = $request->user();
            if ($user !== null && $user->locale !== $locale) {
                $user->forceFill(['locale' => $locale])->save();
            }
        }

        $fallback = route('public.home');

        return back(fallback: $fallback);
    }
}
