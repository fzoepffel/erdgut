<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the restaurant (B2B) area: only a restaurant account that an admin
 * has verified may enter. A restaurant still awaiting review is bounced to
 * the pending page; anyone else is forbidden. Runs after `auth`.
 */
class EnsureUserIsVerifiedRestaurant
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user !== null && $user->isRestaurant(), Response::HTTP_FORBIDDEN);

        if (! $user->isVerifiedRestaurant()) {
            return redirect()->route('app.restaurant.pending');
        }

        return $next($request);
    }
}
