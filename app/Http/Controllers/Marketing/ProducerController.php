<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Producer;
use Illuminate\Contracts\View\View;

class ProducerController extends Controller
{
    /**
     * Show the public producers listing page.
     */
    public function index(): View
    {
        $producers = Producer::query()
            ->select(['name', 'region', 'description', 'image_url'])
            ->orderBy('name')
            ->get();

        return view('producers', [
            'producers' => $producers,
        ]);
    }
}
