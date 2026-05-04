<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track failed payment attempts on spargel orders so the admin list can
 * show "this person tried N times" — without this, a customer who gets
 * declined and clicks "Try again" leaves no visible trace because the
 * SpargelOrderController::pay retry path resets status to PendingPayment.
 *
 * Surfaced in production 2026-05-04 during a real-card test where the
 * order ended up looking "Open" with no indication a payment was tried.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('payment_attempts')->default(0)->after('sumup_payment_id');
            $table->timestamp('last_payment_failed_at')->nullable()->after('payment_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn(['payment_attempts', 'last_payment_failed_at']);
        });
    }
};
