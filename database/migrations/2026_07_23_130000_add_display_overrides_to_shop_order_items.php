<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            // Display-only overrides for the invoice/receipt PDF. The
            // canonical quantity/unit stay untouched so stock, packing and
            // payment stay correct (e.g. order is 300 g, invoice shows "2 Stück").
            $table->string('display_unit_override', 32)->nullable()->after('display_name_override');
            $table->decimal('display_quantity_override', 12, 4)->nullable()->after('display_unit_override');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropColumn(['display_unit_override', 'display_quantity_override']);
        });
    }
};
