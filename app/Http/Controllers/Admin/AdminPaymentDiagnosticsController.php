<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentDiagnostic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only viewer for {@see PaymentDiagnostic} rows.
 *
 * Lets ops grep production widget telemetry without DB access. Filterable
 * by phase + checkout id; results paginate newest-first. Linked rows
 * deep-link to the originating spargel order or payment so the chain of
 * "user complaint → stalled event → order row" is one click.
 */
class AdminPaymentDiagnosticsController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('admin');

        $query = PaymentDiagnostic::query()->latest('id');

        if (filled($request->input('phase'))) {
            $query->where('phase', (string) $request->input('phase'));
        }

        if (filled($request->input('checkout_id'))) {
            $query->where('checkout_id', 'like', '%'.$request->input('checkout_id').'%');
        }

        if (filled($request->input('spargel_order_id'))) {
            $query->where('spargel_order_id', (int) $request->input('spargel_order_id'));
        }

        $diagnostics = $query->paginate(50)->withQueryString();

        $phases = PaymentDiagnostic::query()
            ->select('phase')
            ->distinct()
            ->orderBy('phase')
            ->pluck('phase');

        return view('admin.operations.payment_diagnostics.index', [
            'diagnostics' => $diagnostics,
            'phases' => $phases,
        ]);
    }
}
