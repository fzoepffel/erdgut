<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronRunController extends Controller
{
    /**
     * Secured HTTP trigger for Laravel scheduler (called by cron-job.org).
     * Validates ?key= against config('cron.key'), rejects with 403 if missing/wrong.
     * Liveness is monitored by cron-job.org's own dashboard (per-call
     * status + timestamp + failure email), so no in-app heartbeat needed.
     */
    public function __invoke(): Response
    {
        $key = request()->query('key');
        $expected = config('cron.key');

        if ($expected === null || $key !== $expected) {
            Log::warning('Cron trigger rejected: invalid or missing key.', [
                'has_expected' => $expected !== null,
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 120),
            ]);

            abort(403, 'Invalid or missing cron key');
        }

        // A tick must survive the caller giving up: cron-job.org enforces an
        // HTTP timeout, and without this an abort would kill PHP mid-command
        // (e.g. the queue worker between SMTP send and job deletion).
        ignore_user_abort(true);
        set_time_limit(120);

        Artisan::call('schedule:run');

        return response(Artisan::output(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
