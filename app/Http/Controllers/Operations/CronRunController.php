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
     *
     * Each successful tick is logged so we can confirm the external cron
     * source (IONOS webhosting panel, uptime monitor, …) is hitting us
     * every minute as expected.
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

        $startedAt = microtime(true);

        Artisan::call('schedule:run');
        $output = Artisan::output();

        Log::info('Cron tick executed.', [
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'output_head' => substr($output, 0, 400),
            'ran_something' => ! str_contains($output, 'No scheduled commands are ready to run'),
        ]);

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
