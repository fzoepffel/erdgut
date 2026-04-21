<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\OnboardingDraft;
use App\Models\Order;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(protected OnboardingDraftManager $drafts) {}

    /**
     * Entry point. Records intent from the query, creates a draft if needed,
     * and redirects to the first incomplete step.
     */
    public function start(Request $request): RedirectResponse
    {
        $intent = $request->query('intent');
        if (! in_array($intent, [OnboardingDraftManager::INTENT_TRIAL, OnboardingDraftManager::INTENT_SUBSCRIPTION], true)) {
            $intent = null;
        }

        // Trial disabled by feature flag -> force subscription branch.
        if ($intent === OnboardingDraftManager::INTENT_TRIAL && ! config('features.trial_orders_enabled', true)) {
            $intent = OnboardingDraftManager::INTENT_SUBSCRIPTION;
        }

        $draft = $this->drafts->currentOrCreate($request, $intent);

        if ($fromOrder = $request->query('from_order')) {
            $this->prefillFromOrder($draft, (int) $fromOrder, $request);
        }

        return $this->redirectToNextStep($draft, $request);
    }

    protected function redirectToNextStep(OnboardingDraft $draft, Request $request): RedirectResponse
    {
        $next = $this->drafts->nextStep($draft, $request->user());

        return redirect()->route('app.onboarding.step.'.$next);
    }

    private function prefillFromOrder(OnboardingDraft $draft, int $orderId, Request $request): void
    {
        $user = $request->user();
        if ($user === null) {
            return;
        }

        $order = Order::query()
            ->where('user_id', $user->id)
            ->find($orderId);

        if ($order === null) {
            return;
        }

        $this->drafts->update($draft, [
            'account_completed' => true,
            'address_id' => $order->address_id,
            'address_completed' => true,
            'box_size' => $order->box_size,
            'preferences' => $order->preferences ?? [],
            'allergies' => $order->allergies ?? [],
            'preferences_completed' => true,
            'from_order_id' => $order->id,
        ]);
    }
}
