<?php

namespace App\Http\Controllers;

use App\Models\Box;
use App\Support\BudgetRemainderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoxController extends Controller
{
    public function current(Request $request): RedirectResponse
    {
        $currentBox = Box::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('week_start_date', '<=', now()->toDateString())
            ->whereDate('week_start_date', '>=', now()->subDays(6)->toDateString())
            ->orderByDesc('week_start_date')
            ->first();

        if (! $currentBox instanceof Box) {
            return redirect()
                ->route('app.dashboard')
                ->with('status', 'current-box-missing');
        }

        return redirect()->route('app.boxes.show', $currentBox);
    }

    public function show(Request $request, Box $box, BudgetRemainderService $budgetRemainderService): View
    {
        abort_unless((int) $box->user_id === (int) $request->user()->id, 403);
        $box->loadMissing([
            'items.product.images',
            'items.product.translations',
            'items.product.producer.translations',
        ]);

        $budgetDetails = $budgetRemainderService->getBudgetDetails($request->user(), $box);

        return view('boxes.show', [
            'box' => $box,
            'modificationDeadline' => $box->modification_deadline_at,
            'budgetDetails' => $budgetDetails,
        ]);
    }
}
