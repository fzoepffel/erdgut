<?php

use App\Http\Middleware\EnsureSubscriberHasPayment;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\VerifySumUpWebhookSignature;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withSchedule(function (Schedule $schedule) {
        $schedule->command('boxes:prepare')->dailyAt('00:00')->withoutOverlapping();
        $schedule->command('boxes:lock')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('subscriptions:charge')->dailyAt('06:00')->withoutOverlapping();
        $schedule->command('payments:retry')->dailyAt('08:00')->withoutOverlapping();
        $schedule->command('subscriptions:pause-exhausted')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('notifications:delivery-reminders')->dailyAt('18:00')->withoutOverlapping();
        $schedule->command('notifications:box-deadlines')->dailyAt('10:00')->withoutOverlapping();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->redirectGuestsTo('/auth/login');
        $middleware->redirectUsersTo('/app/dashboard');
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'subscriber.full' => EnsureSubscriberHasPayment::class,
            'sumup.webhook.verify' => VerifySumUpWebhookSignature::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
