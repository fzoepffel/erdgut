<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CronRunController extends Controller
{
    /**
     * Secured HTTP trigger for Laravel scheduler (e.g. IONOS cron).
     * Validates ?key= against config('cron.key'), rejects with 403 if missing/wrong.
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

        Artisan::call('schedule:run');
        $output = Artisan::output();

        // Heartbeat: log one INFO line per successful cron tick so we
        // can detect outages quickly (e.g. silent opcache-stale CRON_KEY
        // rejections like the 2026-05-19 incident). Grep
        // `cron:heartbeat` over the last 30 min — if the count is < 4,
        // something is wrong with IONOS → /cron/run → schedule:run.
        // Output preview lets us also see at-a-glance whether any
        // scheduled commands actually fired on that tick.
        Log::info('cron:heartbeat', [
            'ran_commands' => ! str_contains($output, 'No scheduled commands are ready to run'),
            'output_length' => strlen($output),
            'output_preview' => trim(substr($output, 0, 200)),
        ]);

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
