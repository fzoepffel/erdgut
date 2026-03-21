<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PaymentHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $payments = $request->user()
            ->payments()
            ->with('subscription')
            ->orderByDesc('created_at')
            ->get();

        return view('payments.history', [
            'payments' => $payments,
        ]);
    }
}
