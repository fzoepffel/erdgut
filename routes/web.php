<?php

use App\Http\Controllers\Account\UserAccessProbeController;
use App\Http\Controllers\Admin\AdminBillingCoverageController;
use App\Http\Controllers\Admin\AdminBoxController;
use App\Http\Controllers\Admin\AdminBoxItemController;
use App\Http\Controllers\Admin\AdminBoxSetupController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeliveryController;
use App\Http\Controllers\Admin\AdminEmailResendController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminTrialBoxSetupController;
use App\Http\Controllers\Admin\AdminUpcomingChargeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWeeklyCatalogController;
use App\Http\Controllers\Admin\ProducerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryPreferencesController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\Marketing\ComingSoonController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\ProducerController as MarketingProducerController;
use App\Http\Controllers\Onboarding\AccountStepController;
use App\Http\Controllers\Onboarding\AddressStepController;
use App\Http\Controllers\Onboarding\CommitmentStepController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Onboarding\PreferencesStepController;
use App\Http\Controllers\Onboarding\Subscription\PaymentStepController as SubscriptionWizardPaymentController;
use App\Http\Controllers\Onboarding\Subscription\ScheduleStepController as SubscriptionWizardScheduleController;
use App\Http\Controllers\Onboarding\Trial\DeliveryStepController as TrialWizardDeliveryController;
use App\Http\Controllers\Onboarding\Trial\PaymentStepController as TrialWizardPaymentController;
use App\Http\Controllers\Operations\CronRunController;
use App\Http\Controllers\Operations\HealthcheckController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PaymentSetupController;
use App\Http\Controllers\ProductController as CustomerProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

// Dev-only component gallery / brand playbook. Only mounted in local env so
// it never ships to production or staging routes.
if (app()->environment('local')) {
    Route::view('/dev/components', 'dev.components')->name('dev.components');
}

Route::name('public.')->group(function () {
    Route::get('/cron/run', CronRunController::class)->name('cron.run');
    Route::get('/', ComingSoonController::class)->name('home');
    Route::get('/home', [HomeController::class, 'index'])->name('marketing-home');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/producers', [MarketingProducerController::class, 'index'])->name('producers');
    Route::get('/contact', [ContactController::class, 'create'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

    Route::get('/health', HealthcheckController::class)->name('health');

    Route::get('/locale/{locale}', [LocaleController::class, 'switch'])
        ->whereAlpha('locale')
        ->name('locale.switch');
});

// Onboarding wizard — guest-allowed entry, auth enforced after the account step.
Route::name('app.onboarding.')
    ->prefix('onboard')
    ->group(function () {
        Route::get('/', [OnboardingController::class, 'start'])->name('start');

        Route::get('/account', [AccountStepController::class, 'show'])->name('step.account');
        Route::post('/account', [AccountStepController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('step.account.store');

        Route::middleware('auth')->group(function () {
            Route::get('/address', [AddressStepController::class, 'show'])->name('step.address');
            Route::post('/address', [AddressStepController::class, 'store'])->name('step.address.store');

            Route::get('/preferences', [PreferencesStepController::class, 'show'])->name('step.preferences');
            Route::post('/preferences', [PreferencesStepController::class, 'store'])->name('step.preferences.store');

            Route::get('/commitment', [CommitmentStepController::class, 'show'])->name('step.commitment');
            Route::post('/commitment', [CommitmentStepController::class, 'store'])->name('step.commitment.store');

            Route::get('/trial/delivery', [TrialWizardDeliveryController::class, 'show'])->name('step.trial_delivery');
            Route::post('/trial/delivery', [TrialWizardDeliveryController::class, 'store'])->name('step.trial_delivery.store');

            Route::get('/trial/payment', [TrialWizardPaymentController::class, 'show'])->name('step.trial_payment');
            Route::get('/trial/payment/{order}/complete', [TrialWizardPaymentController::class, 'complete'])
                ->name('trial.payment.complete');
            Route::get('/trial/complete/{order}', [TrialWizardPaymentController::class, 'confirmation'])
                ->name('trial.complete');

            Route::get('/subscription/schedule', [SubscriptionWizardScheduleController::class, 'show'])->name('step.subscription_schedule');
            Route::post('/subscription/schedule', [SubscriptionWizardScheduleController::class, 'store'])->name('step.subscription_schedule.store');

            Route::get('/subscription/payment', [SubscriptionWizardPaymentController::class, 'show'])
                ->middleware('throttle:payment-setup')
                ->name('step.subscription_payment');
            Route::get('/subscription/complete', [SubscriptionWizardPaymentController::class, 'complete'])
                ->middleware('throttle:payment-setup')
                ->name('subscription.complete');
        });
    });

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            // Legacy onboard routes kept as permanent redirects into the wizard.
            Route::get('/onboard', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard');
            Route::post('/onboard', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard.store');
            Route::get('/onboard/payment', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard.payment');
            Route::get('/onboard/complete', fn () => redirect()->route('app.onboarding.start', ['intent' => 'subscription']))->name('onboard.complete');

            Route::get('/', [SubscriptionController::class, 'show'])->name('show');
            Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
            Route::post('/', [SubscriptionController::class, 'store'])->name('store');
            Route::get('/edit', [SubscriptionController::class, 'edit'])->name('edit');
            Route::patch('/', [SubscriptionController::class, 'update'])->name('update');
            Route::post('/pause', [SubscriptionController::class, 'pause'])->middleware('throttle:subscription-actions')->name('pause');
            Route::post('/skip-next', [SubscriptionController::class, 'skipNextWeek'])->middleware('throttle:subscription-actions')->name('skip.next');
            Route::post('/skip-weeks', [SubscriptionController::class, 'updateSkipWeeks'])->middleware('throttle:subscription-actions')->name('skip.weeks');
            Route::post('/resume', [SubscriptionController::class, 'resume'])->middleware('throttle:subscription-actions')->name('resume');
            Route::post('/cancel', [SubscriptionController::class, 'cancel'])->middleware('throttle:subscription-actions')->name('cancel');
        });

        Route::get('products', [CustomerProductController::class, 'index'])->name('products.index');
        Route::get('products/{product}', [CustomerProductController::class, 'show'])->name('products.show');

        Route::middleware('subscriber.full')->group(function () {
            Route::prefix('deliveries')->name('deliveries.')->group(function () {
                Route::get('/', [DeliveryController::class, 'index'])->name('index');
                Route::get('/preferences', [DeliveryPreferencesController::class, 'edit'])->name('preferences.edit');
                Route::patch('/preferences', [DeliveryPreferencesController::class, 'update'])->name('preferences.update');
                Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
                Route::get('/{delivery}/gift', [DeliveryController::class, 'gift'])->name('gift');
                Route::post('/{delivery}/gift', [DeliveryController::class, 'storeGift'])->name('gift.store');
            });
            Route::prefix('boxes')->name('boxes.')->group(function () {
                Route::get('/current', [BoxController::class, 'current'])->name('current');
                Route::get('/{box}', [BoxController::class, 'show'])->name('show');
            });
            Route::prefix('payments')->name('payments.')->group(function () {
                Route::get('/history', [PaymentHistoryController::class, 'index'])->name('history');
                Route::get('/update', [PaymentSetupController::class, 'showUpdate'])->middleware('throttle:payment-setup')->name('update');
            });
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/setup', [PaymentSetupController::class, 'show'])->middleware('throttle:payment-setup')->name('setup');
            Route::get('/setup/complete', [PaymentSetupController::class, 'complete'])->middleware('throttle:payment-setup')->name('setup.complete');
        });

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
            Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
            Route::post('users', [AdminUserController::class, 'storeAdmin'])
                ->middleware('throttle:12,1')
                ->name('users.store-admin');
            Route::delete('users/{user}', [AdminUserController::class, 'destroy'])
                ->whereNumber('user')
                ->middleware('throttle:12,1')
                ->name('users.destroy');
            Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
            Route::post('deliveries/sync-from-boxes', [AdminDeliveryController::class, 'syncFromBoxes'])
                ->middleware('throttle:12,1')
                ->name('deliveries.sync-from-boxes');
            Route::get('deliveries/create', [AdminDeliveryController::class, 'create'])->name('deliveries.create');
            Route::post('deliveries', [AdminDeliveryController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('deliveries.store');
            Route::get('deliveries', [AdminDeliveryController::class, 'index'])->name('deliveries.index');
            Route::get('deliveries/{delivery}/edit', [AdminDeliveryController::class, 'edit'])->name('deliveries.edit');
            Route::patch('deliveries/{delivery}', [AdminDeliveryController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('deliveries.update');
            Route::patch('deliveries/{delivery}/status', [AdminDeliveryController::class, 'updateStatus'])
                ->middleware('throttle:30,1')
                ->name('deliveries.status.update');
            Route::post('deliveries/bulk-delay', [AdminDeliveryController::class, 'bulkDelay'])
                ->middleware('throttle:10,1')
                ->name('deliveries.bulk-delay');
            Route::get('subscriptions/create', [AdminSubscriptionController::class, 'create'])->name('subscriptions.create');
            Route::post('subscriptions', [AdminSubscriptionController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('subscriptions.store');
            Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{payment}', [AdminPaymentController::class, 'show'])->name('payments.show');
            Route::post('payments/{payment}/mark-paid-externally', [AdminPaymentController::class, 'markPaidExternally'])
                ->middleware('throttle:30,1')
                ->name('payments.mark-paid-externally');
            Route::post('payments/{payment}/refund', [AdminPaymentController::class, 'refund'])
                ->middleware('throttle:20,1')
                ->name('payments.refund');
            Route::post('emails/resend', [AdminEmailResendController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('emails.resend');
            Route::get('billing/upcoming', [AdminUpcomingChargeController::class, 'index'])->name('billing.upcoming');
            Route::get('billing/coverage', [AdminBillingCoverageController::class, 'index'])->name('billing.coverage');
            Route::post('billing/charge', [AdminBillingCoverageController::class, 'charge'])
                ->middleware('throttle:12,1')
                ->name('billing.charge');
            Route::get('boxes', [AdminBoxSetupController::class, 'index'])->name('boxes.index');
            Route::get('boxes/setup/{week}', [AdminBoxSetupController::class, 'show'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->name('boxes.setup.show');
            Route::post('boxes/setup/{week}', [AdminBoxSetupController::class, 'apply'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->middleware('throttle:12,1')
                ->name('boxes.setup.apply');
            Route::get('boxes/week/{week}', [AdminBoxSetupController::class, 'weekBoxes'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->name('boxes.week.show');

            Route::get('trial-boxes/setup', [AdminTrialBoxSetupController::class, 'select'])->name('trial-boxes.setup.select');
            Route::get('trial-boxes/setup/{week}', [AdminTrialBoxSetupController::class, 'show'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->name('trial-boxes.setup.show');
            Route::post('trial-boxes/setup/{week}', [AdminTrialBoxSetupController::class, 'apply'])
                ->where('week', '\d{4}-\d{2}-\d{2}')
                ->middleware('throttle:12,1')
                ->name('trial-boxes.setup.apply');
            Route::post('boxes/{box}/items', [AdminBoxItemController::class, 'store'])->name('boxes.items.store');
            Route::patch('boxes/{box}/items/{item}', [AdminBoxItemController::class, 'update'])
                ->scopeBindings()
                ->name('boxes.items.update');
            Route::delete('boxes/{box}/items/{item}', [AdminBoxItemController::class, 'destroy'])
                ->scopeBindings()
                ->name('boxes.items.destroy');
            Route::get('boxes/{box}', [AdminBoxController::class, 'show'])->name('boxes.show');
            Route::patch('boxes/{box}/meta', [AdminBoxController::class, 'updateMeta'])->name('boxes.meta.update');
            Route::post('boxes/{box}/override-edit', [AdminBoxController::class, 'overrideEdit'])
                ->middleware('throttle:30,1')
                ->name('boxes.override-edit');
            Route::resource('producers', ProducerController::class)->except(['show']);
            Route::get('products/weekly/{week?}', [AdminWeeklyCatalogController::class, 'show'])
                ->name('products.weekly.show');
            Route::post('products/weekly/{week}', [AdminWeeklyCatalogController::class, 'save'])
                ->middleware('throttle:24,1')
                ->name('products.weekly.save');
            Route::resource('products', ProductController::class)->except(['show']);

            Route::get('orders/create', [AdminOrderController::class, 'create'])->name('orders.create');
            Route::post('orders', [AdminOrderController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('orders.store');
            Route::get('orders', [AdminOrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
            Route::post('orders/{order}/schedule', [AdminOrderController::class, 'schedule'])
                ->middleware('throttle:30,1')
                ->name('orders.schedule');
            Route::post('orders/{order}/compose', [AdminOrderController::class, 'compose'])
                ->middleware('throttle:30,1')
                ->name('orders.compose');
            Route::post('orders/{order}/mark-delivered', [AdminOrderController::class, 'markDelivered'])
                ->middleware('throttle:30,1')
                ->name('orders.mark-delivered');
        });
    });

Route::middleware('auth')
    ->prefix('account')
    ->name('account.')
    ->group(function () {
        Route::get('/users/{user}', [UserAccessProbeController::class, 'show'])->name('users.show');

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::patch('/', [ProfileController::class, 'update'])->name('update');
            Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        });
    });

require __DIR__.'/auth.php';
