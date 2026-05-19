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
            // TEMPORARY DIAGNOSTIC (debug/cron-key-byte-diff) — logs
            // byte-level details of both keys so we can spot encoding
            // or invisible-character mismatches. Revert this commit
            // once the root cause is found; the bin2hex of the
            // expected key WILL leak the secret to anyone with log
            // access.
            Log::warning('Cron trigger rejected: invalid or missing key.', [
                'has_expected' => $expected !== null,
                'expected_length' => $expected === null ? null : strlen((string) $expected),
                'expected_hex' => $expected === null ? null : bin2hex((string) $expected),
                'received_length' => $key === null ? null : strlen((string) $key),
                'received_hex' => $key === null ? null : bin2hex((string) $key),
                'raw_query_string' => request()->getQueryString(),
                'ip' => request()->ip(),
                'user_agent' => substr((string) request()->userAgent(), 0, 120),
            ]);

            abort(403, 'Invalid or missing cron key');
        }

        Artisan::call('schedule:run');
        $output = Artisan::output();

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
