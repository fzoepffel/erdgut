<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant (B2B) orders are packed on the same product-by-product screen as
 * shop orders — mirror ShopOrderItem's packing state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_order_items', 'packed_at')) {
                $table->timestamp('packed_at')->nullable();
                $table->foreignId('packed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('packed_by_user_id');
            $table->dropColumn('packed_at');
        });
    }
};
