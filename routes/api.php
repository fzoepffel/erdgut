<?php

use App\Http\Controllers\SumUpWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/sumup/webhook', [SumUpWebhookController::class, 'handle'])
    ->middleware('sumup.webhook.verify:subscription')
    ->name('sumup.webhook');
