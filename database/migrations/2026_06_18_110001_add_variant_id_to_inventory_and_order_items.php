<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thread the optional grade (variant_id) through inventory and orders.
 *
 * NULL means the product's default/"Standard" grade everywhere, so existing
 * inflows, schedules and order items keep working unchanged. Box/spargel
 * channels have no variant concept and always draw from the default pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
        });

        Schema::table('product_stock_inflow_schedules', function (Blueprint $table): void {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
        });

        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->foreignId('variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
            // Localized grade label captured at order time so the receipt is
            // stable even if the variant is later renamed or deleted.
            $table->string('variant_name_snapshot')->nullable()->after('variant_id');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('variant_id');
            $table->dropColumn('variant_name_snapshot');
        });

        Schema::table('product_stock_inflow_schedules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('variant_id');
        });

        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
