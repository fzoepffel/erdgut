<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous multi-product shop order.
 *
 * The general-shop successor to `spargel_orders`: same decoupled,
 * email-keyed guest-checkout pattern (no User / Subscription / Order
 * linkage), but the cart can span many catalog products. Line items live
 * in `shop_order_items`; this table holds the customer, fulfilment, and
 * payment-lifecycle data. SumUp tokens stay hidden on the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();

            // Customer (no User row — fully decoupled from auth/onboarding).
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Fulfilment.
            $table->enum('fulfillment_mode', ['pickup', 'delivery']);
            $table->date('expected_delivery_date');
            $table->string('pickup_location')->nullable();
            $table->string('delivery_street')->nullable();
            $table->string('delivery_postal_code', 16)->nullable();
            $table->string('delivery_city')->nullable();
            $table->text('delivery_notes')->nullable();

            // Pricing snapshot (locked at order time so later price changes
            // never retro-apply to in-flight orders). Line breakdown lives
            // on shop_order_items; these are the order-level rollups.
            $table->decimal('items_total_eur', 8, 2);
            $table->decimal('delivery_fee_eur', 6, 2)->default(0);
            $table->decimal('total_amount_eur', 8, 2);
            $table->enum('payment_method', ['online', 'in_person'])->default('online');

            // Lifecycle.
            $table->string('status')->default('pending_payment');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('marked_paid_by_admin_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            // Payment-attempt tracking (parity with spargel_orders).
            $table->unsignedInteger('payment_attempts')->default(0);
            $table->timestamp('last_payment_failed_at')->nullable();

            // Refund tracking.
            $table->decimal('refund_amount', 8, 2)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->string('refund_reason')->nullable();
            $table->foreignId('refunded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('refund_attempts')->default(0);
            $table->timestamp('last_refund_attempt_at')->nullable();
            $table->string('last_refund_error', 500)->nullable();

            // SumUp payment tokens — hidden from API responses via $hidden
            // on the model (same convention as Order / SpargelOrder).
            $table->string('sumup_checkout_id')->nullable();
            $table->string('sumup_payment_id')->nullable();
            $table->json('provider_payload')->nullable();

            // Test-data marker, consistent with the rest of the schema.
            $table->boolean('is_test')->default(false);

            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index('expected_delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
