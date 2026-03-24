<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;

class UserAccessProbeController extends Controller
{
    /**
     * Authorization probe only: returns 204 when the authenticated user may view
     * the target user (see UserPolicy). Not linked from customer navigation; used
     * for tests and ownership checks—not a profile or settings page.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        return response()->noContent();
    }
}
