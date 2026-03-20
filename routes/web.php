<?php

use App\Http\Controllers\Account\UserAccessProbeController;
use App\Http\Controllers\BoxController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\DeliveryPreferencesController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\ProducerController;
use App\Http\Controllers\Operations\HealthcheckController;
use App\Http\Controllers\PaymentSetupController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::name('public.')->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/about', [AboutController::class, 'index'])->name('about');
    Route::get('/producers', [ProducerController::class, 'index'])->name('producers');
    Route::get('/contact', [ContactController::class, 'create'])->name('contact');
    Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

    Route::get('/health', HealthcheckController::class)->name('health');
});

Route::middleware(['auth', 'verified'])
    ->prefix('app')
    ->name('app.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // Placeholder route groups for upcoming ERD business flows.
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/', [SubscriptionController::class, 'show'])->name('show');
            Route::get('/edit', [SubscriptionController::class, 'edit'])->name('edit');
            Route::patch('/', [SubscriptionController::class, 'update'])->name('update');
            Route::post('/pause', [SubscriptionController::class, 'pause'])->name('pause');
            Route::post('/resume', [SubscriptionController::class, 'resume'])->name('resume');
            Route::post('/cancel', [SubscriptionController::class, 'cancel'])->name('cancel');
        });
        Route::prefix('deliveries')->name('deliveries.')->group(function () {
            Route::get('/', [DeliveryController::class, 'index'])->name('index');
            Route::get('/preferences', [DeliveryPreferencesController::class, 'edit'])->name('preferences.edit');
            Route::patch('/preferences', [DeliveryPreferencesController::class, 'update'])->name('preferences.update');
            Route::patch('/{delivery}/override', [DeliveryController::class, 'updateOverride'])->name('override.update');
            Route::get('/{delivery}', [DeliveryController::class, 'show'])->name('show');
            Route::post('/{delivery}/skip', [DeliveryController::class, 'skip'])->name('skip');
            Route::get('/{delivery}/gift', [DeliveryController::class, 'gift'])->name('gift');
        });
        Route::prefix('boxes')->name('boxes.')->group(function () {
            Route::get('/current', [BoxController::class, 'current'])->name('current');
            Route::post('/{box}/items', [BoxController::class, 'addItem'])->name('items.add');
            Route::patch('/{box}/items/{item}', [BoxController::class, 'updateItemQuantity'])
                ->scopeBindings()
                ->name('items.updateQuantity');
            Route::delete('/{box}/items/{item}', [BoxController::class, 'removeItem'])
                ->scopeBindings()
                ->name('items.remove');
            Route::get('/{box}', [BoxController::class, 'show'])->name('show');
        });
        Route::prefix('shop')->name('shop.')->group(function () {
            Route::get('/', [ShopController::class, 'index'])->name('index');
            Route::get('/{product}', [ShopController::class, 'show'])->name('show');
        });
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/setup', [PaymentSetupController::class, 'show'])->name('setup');
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
