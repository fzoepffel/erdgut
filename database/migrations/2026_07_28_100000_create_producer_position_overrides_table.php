<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A manual sourcing override for one receiving position. When several farms
 * could supply the same committed demand, an admin can move a whole position
 * (product + variant + delivery day) to a different producer — and, for a
 * look-alike product, to a different product. The override is the source of
 * truth the receiving panel and the farmer order export both honor; nothing
 * about the placed order or its receipt is rewritten, so it stays reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_position_overrides', function (Blueprint $table) {
            $table->id();
            // The position AS ORDERED: the product/variant the customer bought.
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('variant_id')->default(0);
            $table->date('delivery_date');
            // Where we now source it from. target_product_id equals product_id
            // for a same-product / different-farm move; it differs for a
            // look-alike substitution (e.g. zucchini gelb for zucchini grün).
            $table->foreignId('target_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('target_producer_id')->constrained('producers')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['product_id', 'variant_id', 'delivery_date'],
                'producer_position_overrides_unique',
            );
            $table->index('delivery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_position_overrides');
    }
};
