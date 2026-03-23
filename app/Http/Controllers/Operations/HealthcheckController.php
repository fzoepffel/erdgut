<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthcheckController extends Controller
{
    /**
     * Return lightweight runtime diagnostics for operations checks.
     */
    public function __invoke(): JsonResponse
    {
        $databaseOk = $this->isDatabaseReachable();
        $status = $databaseOk ? 'ok' : 'degraded';

        $checks = ['app' => 'ok'];
        if (config('app.debug')) {
            $checks['database'] = $databaseOk ? 'ok' : 'error';
        }

        return response()->json(
            [
                'status' => $status,
                'checks' => $checks,
                'timestamp' => now()->toIso8601String(),
            ],
            $databaseOk ? 200 : 503
        );
    }

    private function isDatabaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
