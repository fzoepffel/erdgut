<?php

namespace App\Http\Controllers;

use App\Support\Dashboard\DashboardStateResolver;
use App\Support\SubscriberAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardStateResolver $stateResolver) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($request->user()->isAdmin()) {
            return redirect()->route('app.admin.dashboard');
        }

        if ($request->user()->isRestaurant()) {
            return redirect()->route('app.restaurant.dashboard');
        }

        $user = $request->user();

        if (SubscriberAccess::shouldShowWelcomeDashboard($user)) {
            return view('dashboard-welcome');
        }

        $state = $this->stateResolver->resolve($user);

        // Pass the state object directly so the view can ask
        // showsSubscriptionBoxes() / showsTrialBlock() / etc. without
        // re-deriving anything. Backwards-compat names kept so the
        // existing partials in the view don't all have to be renamed.
        return view('dashboard', [
            'state' => $state,
            'dashboardKind' => $state->kind,
            'primarySubscription' => $state->subscription,
            'currentBox' => $state->currentSubscriptionBox,
            'upcomingBoxes' => $state->upcomingSubscriptionBoxes,
            'deliveryCards' => $state->deliveryCards,
            'openTrialOrder' => $state->kind->showsTrialBlock() ? $state->latestTrialOrder : null,
            'trialBox' => $state->trialBox,
            'needsPaymentSetup' => $state->kind->showsResumeSubscriptionSetup(),
        ]);
    }
}
