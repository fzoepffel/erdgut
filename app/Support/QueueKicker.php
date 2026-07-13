<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Makes queued mail near-instant on hosting without a resident worker.
 *
 * Whenever an HTTP request queues a job (see the JobQueued listener in
 * AppServiceProvider), a terminating() callback fires ONE short HTTP request
 * at /cron/queue-kick. That spawns a fresh PHP process which drains the
 * queue immediately; our request only pays ~2s in terminating(), after the
 * response is already on its way. If the kick fails (network, exec limits),
 * nothing is lost: the every-minute scheduler tick delivers as fallback.
 */
class QueueKicker
{
    private static bool $armed = false;

    /** Arm one kick for the end of the current request. */
    public static function kick(): void
    {
        if (self::$armed) {
            return;
        }
        if (app()->runningInConsole() || app()->runningUnitTests()) {
            return; // CLI queues are drained by the same tick; tests stay quiet
        }
        if (config('queue.default') !== 'database' || config('cron.key') === null) {
            return; // sync driver sends inline anyway; no key = no endpoint
        }

        self::$armed = true;
        app()->terminating(function (): void {
            self::fire();
        });
    }

    /** Fire the kick request (fire-and-forget, short timeout, errors swallowed). */
    public static function fire(): void
    {
        try {
            Http::connectTimeout(2)->timeout(2)->get(
                route('public.cron.queue-kick'),
                ['key' => config('cron.key')],
            );
        } catch (Throwable $e) {
            // Expected when the drain outlives our 2s timeout; the endpoint
            // keeps working (ignore_user_abort). Real failures are covered
            // by the cron fallback, so a debug line suffices.
            Log::debug('queue.kick_request_ended', ['message' => $e->getMessage()]);
        }
    }

    /** @internal test helper */
    public static function reset(): void
    {
        self::$armed = false;
    }
}
