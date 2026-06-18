<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One unified table for every conditional price: date/period overrides
     * (Phase 1), customer-group prices (Phase 1), quantity tiers (Phase 2,
     * via min_quantity) and per-variant grade prices (Phase 3, via
     * variant_id). A row is "a price that applies when its conditions match";
     * the PriceResolver picks the winner and falls back to products.unit_price.
     */
    public function up(): void
    {
        Schema::create('product_price_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Phase 3 (grades). Null = applies to the default/standard variant.
            $table->unsignedBigInteger('variant_id')->nullable();
            // Null = every customer type; else 'private' | 'business'.
            $table->string('customer_type', 16)->nullable();
            // Null = open-ended. A single-day override has starts_on == ends_on.
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            // Phase 2 (quantity tiers). Null = applies at any quantity.
            $table->decimal('min_quantity', 12, 4)->nullable();
            // Gross price per CANONICAL unit, same convention as products.unit_price.
            $table->decimal('unit_price', 12, 4);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->string('label')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
            $table->index(['starts_on', 'ends_on']);
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            // Audit: which rule produced the snapshotted unit price (null =
            // base product price or a manual admin override).
            $table->foreignId('price_rule_id')->nullable()->after('unit_price_snapshot_eur')
                ->constrained('product_price_rules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('price_rule_id');
        });

        Schema::dropIfExists('product_price_rules');
    }
};
