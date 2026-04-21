<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ComingSoonController extends Controller
{
    /**
     * Render the pre-launch "coming soon" landing page.
     *
     * Served at the root URL so casual visitors get a branded holding page
     * while the real marketing homepage stays reachable at /home for
     * internal testing and direct-link access.
     */
    public function __invoke(): View
    {
        return view('marketing.coming-soon');
    }
}
