<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Price development per product: every admin save appends a row per price
 * series whose value changed (dedup happens in the recorder, not here).
 * Prices are stored in the product's DISPLAY unit (€/kg, €/Stück) so the
 * chart reads like the shop shelf. Series key: kind + variant + producer.
 *
 *   kind 'base_gross'   B2C sales price incl. VAT   (variant optional)
 *   kind 'business_net' B2B list price excl. VAT    (variant optional)
 *   kind 'buying'       purchase price per farm     (producer set, variant optional)
 *
 * History starts at the first recorded save (or the backfill command);
 * earlier changes were never captured anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('producer_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->decimal('price', 10, 4); // € per display unit
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['product_id', 'kind', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_histories');
    }
};
