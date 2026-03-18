<?php

use App\Http\Controllers\Account\UserAccessProbeController;
use App\Http\Controllers\Marketing\AboutController;
use App\Http\Controllers\Marketing\ContactController;
use App\Http\Controllers\Marketing\HomeController;
use App\Http\Controllers\Marketing\ProducerController;
use App\Http\Controllers\Operations\HealthcheckController;
use App\Http\Controllers\ProfileController;
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
        Route::get('/dashboard', function () {
            return view('dashboard');
        })->name('dashboard');

        // Placeholder route groups for upcoming ERD business flows.
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            //
        });
        Route::prefix('deliveries')->name('deliveries.')->group(function () {
            //
        });
        Route::prefix('boxes')->name('boxes.')->group(function () {
            //
        });
        Route::prefix('shop')->name('shop.')->group(function () {
            //
        });
        Route::prefix('payments')->name('payments.')->group(function () {
            //
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
