<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Response;

class UserAccessProbeController extends Controller
{
    /**
     * Lightweight endpoint to enforce user-ownership authorization.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        return response()->noContent();
    }
}
