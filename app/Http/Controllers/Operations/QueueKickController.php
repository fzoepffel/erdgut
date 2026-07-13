<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Support\QueueKicker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Drains the database queue on demand. Called fire-and-forget by
 * {@see QueueKicker} right after a request queued a job, so
 * mails leave within seconds instead of waiting for the next cron tick
 * (which stays in place as the fallback). Secured by the same key as
 * /cron/run. Concurrent calls are safe: the database queue locks jobs.
 */
class QueueKickController extends Controller
{
    public function __invoke(): Response
    {
        $key = request()->query('key');
        $expected = config('cron.key');

        if ($expected === null || $key !== $expected) {
            Log::warning('Queue kick rejected: invalid or missing key.', [
                'has_expected' => $expected !== null,
                'ip' => request()->ip(),
            ]);

            abort(403, 'Invalid or missing key');
        }

        // The kicker aborts its request after ~2s on purpose; the drain must
        // survive that (same pattern as CronRunController).
        ignore_user_abort(true);
        set_time_limit(120);

        Artisan::call('queue:work', [
            'connection' => 'database',
            '--stop-when-empty' => true,
            '--max-time' => 50,
            '--tries' => 3,
        ]);

        return response('OK', 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
