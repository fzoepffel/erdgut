<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow off-catalog ("custom") order lines on admin-entered orders: a
     * line with no product_id, carrying its own free-text name. Used for
     * ad-hoc B2B items not worth modelling in the catalog (wild herbs, pea
     * tops, …). `unit` already exists and stores the free-text unit; the
     * gross line total + VAT are entered manually like other admin lines.
     */
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->string('name_snapshot', 160)->nullable()->after('variant_name_snapshot');
            $table->unsignedBigInteger('product_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table) {
            $table->dropColumn('name_snapshot');
            // Leave product_id nullable on rollback — re-tightening it would
            // fail if any custom lines exist.
        });
    }
};
