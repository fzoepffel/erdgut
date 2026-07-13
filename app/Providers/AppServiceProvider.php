<?php

namespace App\Providers;

use App\Http\Controllers\Admin\AdminShopOrderController;
use App\Http\Controllers\SpargelAdminController;
use App\Listeners\AddListUnsubscribeHeader;
use App\Listeners\LogSentMail;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\SpargelOrder;
use App\Models\User;
use App\Observers\CatalogAuditObserver;
use App\Observers\InventoryCacheObserver;
use App\Observers\ShopOrderObserver;
use App\Observers\SpargelOrderObserver;
use App\Policies\ProducerPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use App\Services\Shop\ShopOrderService;
use App\Services\SpargelOrderService;
use App\Support\QueueKicker;
use App\Support\RequiredSecretValidator;
use App\Support\SubscriberAccess;
use App\Support\SumUpService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Default SumUpService binding → sandbox credentials for subscriptions/trials.
        $this->app->singleton(SumUpService::class, fn () => new SumUpService('subscription'));

        // Named live instance for the spargel shop.
        $this->app->singleton('sumup.spargel', fn () => new SumUpService('spargel'));

        // Contextual bindings: spargel + shop classes receive the live
        // service automatically. The shop sells through the same live
        // SumUp merchant as the spargel trial channel.
        $this->app->when([
            SpargelOrderService::class,
            SpargelAdminController::class,
            ShopOrderService::class,
            AdminShopOrderController::class,
        ])
            ->needs(SumUpService::class)
            ->give(fn ($app) => $app->make('sumup.spargel'));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Producer::class, ProducerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        Product::observe(CatalogAuditObserver::class);
        Producer::observe(CatalogAuditObserver::class);
        SpargelOrder::observe(SpargelOrderObserver::class);
        ShopOrder::observe(ShopOrderObserver::class);

        // Invalidate the cached shop availability matrix on any write that can
        // change inventory: inflows/schedules (supply), shop/spargel orders +
        // their items and box items (demand), and products (visibility/set).
        foreach ([
            ProductStockInflow::class,
            ProductStockInflowSchedule::class,
            ShopOrder::class,
            ShopOrderItem::class,
            RestaurantOrder::class,
            RestaurantOrderItem::class,
            SpargelOrder::class,
            BoxItem::class,
            Product::class,
        ] as $inventoryModel) {
            $inventoryModel::observe(InventoryCacheObserver::class);
        }

        Event::listen(MessageSending::class, AddListUnsubscribeHeader::class);
        // ERD-350: per-user mail history surfaces in /app/admin/users/{user}
        // via audit_events. Listener writes a 'mail.sent' row per recipient
        // that maps to a known User.
        Event::listen(MessageSent::class, LogSentMail::class);

        // A request that queues a job kicks the queue endpoint once at
        // terminate time, so queued mail leaves within seconds instead of
        // waiting for the next cron tick. {@see \App\Support\QueueKicker}
        Event::listen(JobQueued::class, fn () => QueueKicker::kick());

        // Currency Blade directives — keep template rendering one-liner.
        // {@see \App\Support\Money} is the canonical German-format helper.
        Blade::directive('eur', fn (string $expression) => "<?php echo \\App\\Support\\Money::formatEur({$expression}); ?>");
        Blade::directive('eurCents', fn (string $expression) => "<?php echo \\App\\Support\\Money::formatCents({$expression}); ?>");
        Blade::directive('money', fn (string $expression) => "<?php echo \\App\\Support\\Money::format({$expression}); ?>");

        Gate::define('admin', static fn (User $user): bool => $user->isAdmin());

        View::composer('layouts.app', function (\Illuminate\View\View $view): void {
            $view->with(
                'hasSubscriberNav',
                SubscriberAccess::hasFullSubscriberExperience(auth()->user())
            );
        });

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
