<?php

namespace App\Http\Controllers;

use App\Models\Box;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class BoxController extends Controller
{
    public function show(Request $request, Box $box): View
    {
        abort_unless((int) $box->user_id === (int) $request->user()->id, 403);

        return view('boxes.show', [
            'box' => $box,
        ]);
    }
}
