<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            // The exact net unit price the admin typed in the invoice editor,
            // per the line's natural display unit (e.g. 21,5000 €/kg). Kept at
            // full precision so the printed unit price is not reconstructed as
            // line_total ÷ quantity, which amplifies the 2-decimal money
            // rounding for small quantities (0,65 ÷ 0,03 kg = 21,67, not 21,50).
            // Null falls back to the catalog price / effective rate.
            $table->decimal('display_unit_price_net_eur', 12, 4)->nullable()->after('display_quantity_override');
        });
    }

    public function down(): void
    {
        Schema::table('shop_order_items', function (Blueprint $table): void {
            $table->dropColumn('display_unit_price_net_eur');
        });
    }
};
