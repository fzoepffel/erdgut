<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::name('public.')->group(function () {
    Route::get('/', function () {
        return view('welcome');
    })->name('home');
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
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'edit'])->name('edit');
            Route::patch('/', [ProfileController::class, 'update'])->name('update');
            Route::delete('/', [ProfileController::class, 'destroy'])->name('destroy');
        });
    });

require __DIR__.'/auth.php';
