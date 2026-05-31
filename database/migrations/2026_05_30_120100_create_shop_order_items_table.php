<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items for an anonymous shop order.
 *
 * One row per product in the cart. `quantity` is stored in the product's
 * *canonical* unit (g / ml / piece / bunch) — the same unit as
 * `product_stock_inflows.quantity` and `box_items.quantity` — so the
 * InventoryService outflow math stays unit-consistent across channels.
 * Price + unit are snapshotted so a later catalog edit can't rewrite the
 * receipt of an order already placed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_order_id')->constrained('shop_orders')->cascadeOnDelete();

            // Keep the product reference but restrict deletes — a product
            // that's been sold should not be hard-deletable out from under
            // its order history.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Quantity in canonical units of the product's unit kind.
            $table->decimal('quantity', 10, 4);

            // Snapshots taken at order time.
            $table->string('unit'); // ProductUnit value: mass|volume|piece|bunch
            $table->decimal('unit_price_snapshot_eur', 10, 4); // per canonical unit
            $table->decimal('line_total_eur', 8, 2);

            $table->timestamps();

            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_items');
    }
};
