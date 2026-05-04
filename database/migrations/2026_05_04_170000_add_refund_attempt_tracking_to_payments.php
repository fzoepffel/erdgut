<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track failed refund attempts on subscription / order payments.
 *
 * Mirrors the spargel-orders flow shipped earlier on 2026-05-04: the
 * old AdminPaymentController::refund flipped local status to refunded
 * BEFORE asking the provider, so an admin click looked successful even
 * when SumUp rejected the API call. Now we try SumUp first and, on
 * failure, record the attempt here so the admin sees "refund failed —
 * retry" instead of a misleading green badge.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedInteger('refund_attempts')->default(0)->after('refunded_by_user_id');
            $table->timestamp('last_refund_attempt_at')->nullable()->after('refund_attempts');
            $table->string('last_refund_error', 500)->nullable()->after('last_refund_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['refund_attempts', 'last_refund_attempt_at', 'last_refund_error']);
        });
    }
};
