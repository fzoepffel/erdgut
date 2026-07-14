<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Buying price per (farm, grade): what Erdgut pays THIS farm for THIS
 * grade of the product. Same semantics as products.buying_price_cents
 * and product_producer.buying_price_cents (display-unit euros times
 * 100). A row existing means the override is set; resolution chain is
 * (farm, grade) → farm pivot price → product default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_producer_variant_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->unsignedInteger('buying_price_cents');
            $table->timestamps();

            // Default constraint name would exceed MySQL's 64-char limit.
            $table->unique(['product_id', 'producer_id', 'variant_id'], 'ppvp_product_producer_variant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_producer_variant_prices');
    }
};
