<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

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
            abort(403, 'Invalid or missing cron key');
        }

        Artisan::call('schedule:run');
        $output = Artisan::output();

        return response($output, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
