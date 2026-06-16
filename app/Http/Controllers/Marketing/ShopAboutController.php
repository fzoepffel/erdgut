<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ShopAboutController extends Controller
{
    /**
     * Show the public "About Erdgut" page for the shop side.
     *
     * Mirrors the editorial almanac treatment of the box About page
     * (public.about) but revamped to present Erdgut and its shop rather
     * than the weekly subscription box. The box About page stays at /about.
     */
    public function __invoke(): View
    {
        return view('marketing.shop-about');
    }
}
