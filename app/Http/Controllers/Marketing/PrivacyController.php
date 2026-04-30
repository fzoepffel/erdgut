<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Minimal privacy notice for the spargel trial-shop. Not a full GDPR
 * privacy policy — just enough to satisfy the consent link on the
 * checkout form (we name the data we collect, the legal basis, and a
 * contact for exercising data-subject rights).
 *
 * Replace with a full notice once the rest of the marketing site goes
 * live.
 */
class PrivacyController extends Controller
{
    public function show(): View
    {
        return view('marketing.privacy');
    }
}
