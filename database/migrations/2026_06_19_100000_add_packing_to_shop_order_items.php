<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line packing state for the product-by-product packing screen. NULL
 * packed_at = not yet packed. An order is "ready" when all its items are
 * packed (derived, not a stored status — fulfillment stays a manual step).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->timestamp('packed_at')->nullable()->after('vat_amount_eur');
            $table->foreignId('packed_by_user_id')->nullable()->after('packed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('packed_by_user_id');
            $table->dropColumn('packed_at');
        });
    }
};
