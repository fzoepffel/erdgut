<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\RequiredSecretValidator;
use Illuminate\Support\Facades\Gate;
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
}
