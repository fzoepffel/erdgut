<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Minimal AGB (Allgemeine Geschäftsbedingungen) page. Exists so the
 * payment-step consent checkbox (B12 in the UX review notes) can link
 * to a real page — German B2C recurring billing requires explicit
 * acceptance of terms tied to a stable URL.
 *
 * Replace with a full T&Cs document reviewed by counsel before launch.
 */
class TermsController extends Controller
{
    public function show(): View
    {
        return view('marketing.terms');
    }
}
