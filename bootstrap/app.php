<?php

// Local dev only: PHP 8.5 raises deprecation notices for renamed PDO
// constants used inside vendor/laravel/framework/config/database.php
// (e.g. PDO::MYSQL_ATTR_SSL_CA → Pdo\Mysql\ATTR_SSL_CA). Until the
// framework lands the rename, these notices leak into rendered HTML
// when php artisan serve is used (it spawns php -S which ignores
// .user.ini and inherited -d flags). Suppress at the earliest hook
// so customer-facing screenshots aren't polluted in dev.
if (($_SERVER['APP_ENV'] ?? null) === 'local' || (getenv('APP_ENV') ?: null) === 'local') {
    // E_STRICT itself was removed in PHP 8.4 — don't reference it here.
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

use App\Http\Middleware\EnsureOnboardingDraft;
use App\Http\Middleware\EnsureSubscriberHasPayment;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RedirectIfOnboardingComplete;
use App\Http\Middleware\RedirectToCorrectOnboardingStep;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
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
        $schedule->command('sumup:reconcile-pending-payments')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('subscriptions:pause-exhausted')->dailyAt('09:00')->withoutOverlapping();
        $schedule->command('notifications:delivery-reminders')->dailyAt('18:00')->withoutOverlapping();
        $schedule->command('notifications:box-deadlines')->dailyAt('10:00')->withoutOverlapping();
        $schedule->command('trials:send-conversion-reminders')->dailyAt('11:00')->withoutOverlapping();
        $schedule->command('onboarding:prune-drafts')->dailyAt('03:30')->withoutOverlapping();
        $schedule->command('dashboard:purge-click-events')->dailyAt('03:45')->withoutOverlapping();
    })
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetLocale::class]);
        $middleware->redirectGuestsTo('/auth/login');
        $middleware->redirectUsersTo('/app/dashboard');
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'subscriber.full' => EnsureSubscriberHasPayment::class,
            'sumup.webhook.verify' => VerifySumUpWebhookSignature::class,
            'onboarding.draft' => EnsureOnboardingDraft::class,
            'onboarding.step' => RedirectToCorrectOnboardingStep::class,
            'onboarding.complete.redirect' => RedirectIfOnboardingComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
