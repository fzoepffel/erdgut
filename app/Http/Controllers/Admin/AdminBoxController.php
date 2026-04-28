<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminBoxOverrideEditRequest;
use App\Models\Box;
use App\Models\Product;
use App\Models\Subscription;
use App\Support\AuditLogger;
use App\Support\WeeklyDistributionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminBoxController extends Controller
{
    public function show(Box $box, WeeklyDistributionService $distribution): View
    {
        $this->authorize('admin');

        $box->load([
            'user:id,name,email,phone',
            'items' => fn ($q) => $q->orderBy('id')->with('product:id,allergens,dietary_tags'),
            'items.product.translations',
        ]);

        $allergies = array_values((array) ($box->allergies_snapshot ?? []));
        $preferences = (array) ($box->preferences_snapshot ?? []);
        $itemConflicts = $box->items
            ->mapWithKeys(fn ($item) => [
                $item->id => $item->product !== null
                    && $distribution->conflictsWithSnapshot($item->product, $allergies, $preferences),
            ])
            ->all();

        $catalogQuery = Product::query();
        if ($box->week_start_date !== null) {
            $catalogQuery->availableForWeek($box->week_start_date);
        } else {
            $catalogQuery->visibleInCatalog();
        }
        $locale = app()->getLocale();
        $catalogProducts = $catalogQuery
            ->select('products.*')
            ->join('product_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
            })
            ->with('translations')
            ->orderBy('pt.name')
            ->get(['products.id']);

        $subscription = Subscription::query()
            ->where('user_id', $box->user_id)
            ->where('status', SubscriptionStatus::Active)
            ->latest()
            ->first();

        $marginRate = (float) config('box.admin_planning_margin_rate', 0);
        $budgetDetails = null;
        if ($subscription !== null) {
            $included = round((float) $subscription->includedValueForBudget(), 2);
            $estimated = round((float) $box->estimated_value, 2);
            $budgetDetails = [
                'included_budget' => $included,
                'estimated_value' => $estimated,
                'remainder' => round($included - $estimated, 2),
                'over_budget' => $estimated > $included + 0.005,
                'planning_hint' => $marginRate > 0 && $marginRate < 1
                    ? round($included * (1 - $marginRate), 2)
                    : null,
            ];
        }

        return view('admin.operations.boxes.show', [
            'box' => $box,
            'subscription' => $subscription,
            'catalogProducts' => $catalogProducts,
            'adminCanMutateItems' => $box->allowsAdminLineEdits(),
            'budgetDetails' => $budgetDetails,
            'itemConflicts' => $itemConflicts,
        ]);
    }

    public function overrideEdit(AdminBoxOverrideEditRequest $request, Box $box): RedirectResponse
    {
        $this->authorize('admin');

        $data = $request->validated();
        $minutes = (int) ($data['minutes'] ?? (int) config('admin.box_edit_override_minutes', 30));
        $minutes = max(5, min(240, $minutes));

        $previous = $box->admin_edit_override_until?->toIso8601String();
        $until = now()->addMinutes($minutes);

        $box->forceFill(['admin_edit_override_until' => $until])->save();

        AuditLogger::log(
            entityType: 'box',
            entityId: $box->id,
            action: 'admin_override_window',
            fromState: $previous,
            toState: $until->toIso8601String(),
            reason: $data['reason'] ?? null,
            metadata: [
                'minutes' => $minutes,
                'box_status' => $box->status->value,
                'modification_deadline_at' => $box->modification_deadline_at?->toIso8601String(),
            ],
            actorUserId: $request->user()?->id,
        );

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', 'box-edit-override-opened');
    }

    public function updateMeta(Request $request, Box $box): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'next_update_expected_at' => ['nullable', 'date'],
        ]);

        $box->update([
            'next_update_expected_at' => $validated['next_update_expected_at'] ?? null,
        ]);

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', 'box-meta-updated');
    }
}
