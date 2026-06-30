<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Line items for a restaurant (B2B) order.
 *
 * `quantity` is in the product's canonical unit (g/ml/piece/bunch) — same as
 * shop_order_items / product_stock_inflows — so the InventoryService outflow
 * math stays unit-consistent across channels. `track` splits the two-track
 * model: `reserved` lines hard-reserve stock (counted by InventoryService);
 * `requested` lines are out-of-stock requests that reserve nothing. Price +
 * unit are snapshotted (gross per canonical unit) so a later catalog edit
 * can't rewrite a submitted request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_order_id')->constrained('restaurant_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('variant_name_snapshot')->nullable();

            $table->decimal('quantity', 10, 4); // canonical units
            $table->string('unit'); // ProductUnit value
            $table->string('track', 12); // reserved | requested

            $table->decimal('unit_price_snapshot_eur', 10, 4); // gross per canonical unit
            $table->unsignedTinyInteger('vat_rate_snapshot_percent')->default(7);
            $table->decimal('line_total_eur', 8, 2); // gross
            $table->decimal('vat_amount_eur', 8, 2)->default(0);

            $table->foreignId('price_rule_id')->nullable()->constrained('product_price_rules')->nullOnDelete();

            $table->timestamps();

            $table->index('product_id');
            $table->index(['restaurant_order_id', 'track']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_order_items');
    }
};
