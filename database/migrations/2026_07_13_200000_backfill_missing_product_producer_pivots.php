<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Safety net for products created between the P1 backfill and the P5 form
 * rework: the admin controller wrote products.producer_id without mirroring
 * it into the product_producer pivot. Give every such product its primary
 * pivot row. Idempotent (insertOrIgnore over the (product_id, producer_id)
 * unique index), portable across MySQL and SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->whereNotNull('producer_id')
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    DB::table('product_producer')->insertOrIgnore([
                        'product_id' => $product->id,
                        'producer_id' => $product->producer_id,
                        'position' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data backfill; nothing sensible to reverse.
    }
};
