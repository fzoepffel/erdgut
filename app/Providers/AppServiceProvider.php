<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\User;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use App\Support\RequiredSecretValidator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        Gate::define('admin', static fn (User $user): bool => $user->isAdmin());

        $this->configureRateLimiting();

        if (in_array(app()->environment(), ['production', 'staging'], true)) {
            URL::forceScheme('https');
        }

        $missingSecrets = (new RequiredSecretValidator)->missingForEnvironment(
            app()->environment(),
            [
                'APP_KEY' => config('app.key'),
                'CRON_KEY' => config('cron.key'),
            ]
        );

        if ($missingSecrets !== []) {
            throw new RuntimeException(
                sprintf(
                    'Missing required secrets for %s: %s',
                    app()->environment(),
                    implode(', ', $missingSecrets)
                )
            );
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('contact', static fn (Request $request) => Limit::perMinute(5)->by($request->ip()));

        RateLimiter::for('payment-setup', static fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('subscription-actions', static fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
    }
}
