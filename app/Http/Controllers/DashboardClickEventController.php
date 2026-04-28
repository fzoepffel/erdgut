<?php

namespace App\Http\Controllers;

use App\Models\DashboardClickEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Records a single click on the customer dashboard.
 *
 * The frontend uses navigator.sendBeacon (or fetch with keepalive) so the
 * click doesn't get cancelled by the navigation it's reporting. The
 * endpoint must be cheap and never block the user's actual click.
 */
class DashboardClickEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Auth is enforced by the route middleware ('auth'); we still
        // tolerate unauth here defensively (e.g. session expired between
        // page load and click) and just no-op with 204.
        $user = $request->user();
        if ($user === null) {
            return response()->json(['ok' => true], 204);
        }

        $data = $request->validate([
            'section' => ['required', 'string', 'max:50'],
            'destination' => ['required', 'string', 'max:100'],
        ]);

        DashboardClickEvent::query()->create([
            'user_id' => $user->id,
            'section' => $data['section'],
            'destination' => $data['destination'],
            'locale' => app()->getLocale(),
            'occurred_at' => Carbon::now(),
        ]);

        return response()->json(['ok' => true], 201);
    }
}
