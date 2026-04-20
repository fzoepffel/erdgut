<?php

use App\Http\Controllers\Account\UserAccessProbeController;
use App\Http\Controllers\Admin\AdminBillingCoverageController;
use App\Http\Controllers\Admin\AdminBoxController;
use App\Http\Controllers\Admin\AdminBoxItemController;
use App\Http\Controllers\Admin\AdminBoxSetupController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeliveryController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminUpcomingChargeController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdminWeeklyCatalogController;
use App\Http\Controllers\Admin\ProducerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryPreferencesController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\ProducerController as MarketingProducerController;
use App\Http\Controllers\Operations\CronRunController;
use App\Http\Controllers\Operations\HealthcheckController;
use App\Http\Controllers\PaymentHistoryController;
use App\Http\Controllers\PaymentSetupController;
use App\Http\Controllers\ProductController as CustomerProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SubscriptionOnboardController;
use Illuminate\Support\Facades\Route;

Route::name('public.')->group(function () {
    Route::get('/cron/run', CronRunController::class)->name('cron.run');
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/producers', [MarketingProducerController::class, 'index'])->name('producers');
    Route::get('/contact', [ContactController::class, 'create'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

    Route::get('/health', HealthcheckController::class)->name('health');
});

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/onboard', [SubscriptionOnboardController::class, 'showStep1'])->name('onboard');
            Route::post('/onboard', [SubscriptionOnboardController::class, 'storeStep1'])
                ->middleware('throttle:subscription-actions')
                ->name('onboard.store');
            Route::get('/onboard/payment', [SubscriptionOnboardController::class, 'showStep2'])
                ->middleware('throttle:payment-setup')
                ->name('onboard.payment');
            Route::get('/onboard/complete', [SubscriptionOnboardController::class, 'complete'])
                ->middleware('throttle:payment-setup')
                ->name('onboard.complete');

            Route::get('/', [SubscriptionController::class, 'show'])->name('show');
            Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
            Route::post('/', [SubscriptionController::class, 'store'])->name('store');
            Route::get('/edit', [SubscriptionController::class, 'edit'])->name('edit');
            Route::patch('/', [SubscriptionController::class, 'update'])->name('update');
            Route::post('/pause', [SubscriptionController::class, 'pause'])->middleware('throttle:subscription-actions')->name('pause');
            Route::post('/skip-next', [SubscriptionController::class, 'skipNextWeek'])->middleware('throttle:subscription-actions')->name('skip.next');
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
            Route::get('payments', [AdminPaymentController::class, 'index'])->name('payments.index');
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
            Route::post('boxes/{box}/items', [AdminBoxItemController::class, 'store'])->name('boxes.items.store');
            Route::patch('boxes/{box}/items/{item}', [AdminBoxItemController::class, 'update'])
                ->scopeBindings()
                ->name('boxes.items.update');
            Route::delete('boxes/{box}/items/{item}', [AdminBoxItemController::class, 'destroy'])
                ->scopeBindings()
                ->name('boxes.items.destroy');
            Route::get('boxes/{box}', [AdminBoxController::class, 'show'])->name('boxes.show');
            Route::resource('producers', ProducerController::class)->except(['show']);
            Route::get('products/weekly/{week?}', [AdminWeeklyCatalogController::class, 'show'])
                ->name('products.weekly.show');
            Route::post('products/weekly/{week}', [AdminWeeklyCatalogController::class, 'save'])
                ->middleware('throttle:24,1')
                ->name('products.weekly.save');
            Route::resource('products', ProductController::class)->except(['show']);
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
