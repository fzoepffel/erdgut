<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anonymous per-product daily engagement counters that feed the shop's
 * product-ranking bandit (margin-per-impression Thompson sampling).
 *
 * One row per (product, day): how many times the product card was actually
 * seen (`impressions`, via a viewport beacon) and how many times it was first
 * added to a cart (`add_to_carts`). No user IDs, no per-visitor rows, no
 * cross-site data — purely aggregate counts, so no consent surface is added.
 * Time decay for the ranking is applied at read time by weighting each
 * day-row by its age, which is why the grain is daily rather than per-event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_engagement_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'day']);
            $table->index('day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_engagement_daily');
    }
};
