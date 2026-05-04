<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track failed refund attempts on spargel orders.
 *
 * Surfaced 2026-05-04 in production: the previous refund flow flipped
 * status to Refunded BEFORE knowing if SumUp accepted the API call, so
 * an admin who clicked Refund saw the order marked refunded even though
 * SumUp rejected the request (e.g. NOT_ENOUGH_BALANCE while funds are
 * still pending settlement). Now the flow tries SumUp first, only
 * promotes the status if SumUp confirms, and otherwise records the
 * attempt here so the admin sees a "refund failed" warning + retry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('refund_attempts')->default(0)->after('refunded_by_user_id');
            $table->timestamp('last_refund_attempt_at')->nullable()->after('refund_attempts');
            $table->string('last_refund_error', 500)->nullable()->after('last_refund_attempt_at');
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn(['refund_attempts', 'last_refund_attempt_at', 'last_refund_error']);
        });
    }
};
