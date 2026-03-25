<?php

namespace App\Http\Middleware;

use App\Support\SubscriberAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restrict routes to users with a non-cancelled subscription and a saved payment token.
 */
class EnsureSubscriberHasPayment
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! SubscriberAccess::hasFullSubscriberExperience($user)) {
            return redirect()
                ->route('app.dashboard')
                ->with('status', 'subscriber-area-requires-setup');
        }

        return $next($request);
    }
}
