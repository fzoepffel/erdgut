<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class HomeController extends Controller
{
    /**
     * Show the public marketing homepage.
     */
    public function index(): View
    {
        return view('marketing.home');
    }
}
