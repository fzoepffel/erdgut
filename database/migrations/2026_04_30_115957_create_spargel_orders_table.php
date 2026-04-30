<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone trial-shop table for the first spargel sales test.
 *
 * Intentionally NOT linked to users / orders / products / producers — this is
 * a one-off test channel for selling asparagus before the full subscription
 * flow goes live. Customer email lives directly on the row so we don't have
 * to spin up User accounts for buyers we haven't onboarded yet.
 *
 * Fulfilment + product details (which farmer, harvest date, etc.) are handled
 * manually outside the app for this trial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spargel_orders', function (Blueprint $table) {
            $table->id();

            // Customer (no User row created — fully decoupled from the
            // existing auth + onboarding flow).
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Product choice.
            $table->enum('asparagus_type', ['green', 'white']);
            $table->decimal('quantity_kg', 5, 2);

            // Pricing snapshot (locked at order time so a price change
            // doesn't retro-apply to in-flight orders).
            $table->decimal('unit_price_eur', 6, 2);
            $table->enum('fulfillment_mode', ['pickup', 'delivery']);
            $table->decimal('delivery_fee_eur', 5, 2)->default(0);
            $table->decimal('total_amount_eur', 7, 2);

            // Delivery address (only populated when fulfillment_mode = delivery).
            $table->string('delivery_street')->nullable();
            $table->string('delivery_postal_code', 16)->nullable();
            $table->string('delivery_city')->nullable();
            $table->text('delivery_notes')->nullable();

            // Lifecycle.
            $table->string('status')->default('pending_payment');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();

            // SumUp payment tokens — kept hidden from API responses via
            // $hidden on the model (same convention as Order/Subscription).
            $table->string('sumup_checkout_id')->nullable();
            $table->string('sumup_payment_id')->nullable();
            $table->json('provider_payload')->nullable();

            $table->timestamps();

            $table->index('email');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spargel_orders');
    }
};
