<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on `sumup_checkout_id` for both `payments` and
 * `spargel_orders`.
 *
 * The PaymentDiagnosticsController looks up an order/payment row by
 * `sumup_checkout_id` on every browser-side telemetry beacon (mounted,
 * sdk_loaded, widget_ready, submit_clicked, every 3s processing
 * heartbeat, response_*, stalled, …). Without an index, each beacon
 * triggered TWO full table scans — one against `payments`, one against
 * `spargel_orders`. With even moderate growth, that pegged the DB on
 * IONOS shared hosting and slowed every concurrent request, including
 * the marketing homepage's session lookup.
 *
 * Also indexing `payments.sumup_payment_id` because the same lookup
 * pattern shows up in the SumUp webhook handler and admin refund
 * queries; cheap to add now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index('sumup_checkout_id', 'payments_sumup_checkout_id_index');
            $table->index('sumup_payment_id', 'payments_sumup_payment_id_index');
        });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->index('sumup_checkout_id', 'spargel_orders_sumup_checkout_id_index');
            $table->index('sumup_payment_id', 'spargel_orders_sumup_payment_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_sumup_checkout_id_index');
            $table->dropIndex('payments_sumup_payment_id_index');
        });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropIndex('spargel_orders_sumup_checkout_id_index');
            $table->dropIndex('spargel_orders_sumup_payment_id_index');
        });
    }
};
