<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A restaurant (B2B) order/request submitted by a verified restaurant account.
 *
 * Unlike the payment-centric, anonymous {@see ShopOrder}, this is tied to a
 * user account and has no payment: a restaurant submits a request, in-stock
 * lines are hard-reserved on submit (see restaurant_order_items.track), and an
 * admin later confirms + invoices it. Delivery only — the address is snapshot
 * from the restaurant profile at submit time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('submitted')->index();

            $table->date('expected_delivery_date')->index();

            // Delivery address snapshot (prefilled from the profile, editable).
            $table->string('delivery_street');
            $table->string('delivery_postal_code', 16);
            $table->string('delivery_city');
            $table->text('delivery_notes')->nullable();

            // Order-level rollups. Prices are gross (VAT-inclusive); net + VAT
            // are derived from the line snapshots at submit time.
            $table->decimal('items_total_net_eur', 8, 2)->default(0);
            $table->decimal('items_total_gross_eur', 8, 2)->default(0);
            $table->decimal('vat_total_eur', 8, 2)->default(0);

            $table->string('locale', 5)->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_orders');
    }
};
