<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function show(Request $request, Delivery $delivery): View
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        return view('deliveries.show', [
            'delivery' => $delivery,
        ]);
    }

    public function skip(Request $request, Delivery $delivery): RedirectResponse
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        return redirect()
            ->route('app.dashboard')
            ->with('status', 'skip-week-coming-soon');
    }

    public function gift(Request $request, Delivery $delivery): View
    {
        abort_unless((int) $delivery->user_id === (int) $request->user()->id, 403);

        return view('deliveries.gift', [
            'delivery' => $delivery,
        ]);
    }
}
