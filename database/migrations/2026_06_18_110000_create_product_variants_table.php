<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product grades/variants (Pricing Phase 3).
 *
 * A variant is a named sub-option of a product (e.g. "Thin / Grade B"
 * asparagus) sold at its own price with its own stock. The product itself is
 * the implicit default/"Standard" grade (variant_id NULL everywhere), so this
 * table holds only the *extra* named grades — no per-product default row, and
 * no data migration for existing inventory/orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Localized grade label: {"de": "Dünn", "en": "Thin", "fr": "Fines"}.
            $table->json('name');
            // Gross price per CANONICAL unit (same convention as
            // products.unit_price). NULL inherits the product's base price.
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
