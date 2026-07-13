<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Supplied by" registry: one product can be delivered by several producers.
 * position 0 is the primary producer and is mirrored into
 * products.producer_id (kept as an eager-loadable display fallback) by
 * {@see Product::syncProducers()}.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_producer')) {
            return;
        }

        Schema::create('product_producer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'producer_id'], 'product_producer_pair_unique');
            $table->index('producer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_producer');
    }
};
