<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class AboutController extends Controller
{
    /**
     * Show the public About page.
     */
    public function index(): View
    {
        return view('about');
    }
}
