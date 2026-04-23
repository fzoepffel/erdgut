<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('box_items', 'unit_price_snapshot')) {
            Schema::table('box_items', function (Blueprint $table) {
                $table->decimal('unit_price_snapshot', 10, 2)->nullable()->after('unit');
            });
        }

        // Backfill from current product price (acceptable imperfection for historical rows).
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                UPDATE box_items bi
                INNER JOIN products p ON p.id = bi.product_id
                SET bi.unit_price_snapshot = p.unit_price
                WHERE bi.unit_price_snapshot IS NULL
            ');
        } else {
            DB::statement('
                UPDATE box_items
                SET unit_price_snapshot = (
                    SELECT unit_price FROM products WHERE products.id = box_items.product_id
                )
                WHERE unit_price_snapshot IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('box_items', function (Blueprint $table) {
            $table->dropColumn('unit_price_snapshot');
        });
    }
};
