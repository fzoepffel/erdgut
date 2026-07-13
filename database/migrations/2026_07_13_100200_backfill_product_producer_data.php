<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seed the multi-producer world from the current 1:1 state: every product's
 * single producer becomes its primary pivot row, and every existing inflow /
 * schedule is stamped with that producer. Idempotent (insertOrIgnore +
 * whereNull), portable across MySQL and SQLite (no join-UPDATE syntax).
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
                    DB::table('product_stock_inflows')
                        ->where('product_id', $product->id)
                        ->whereNull('producer_id')
                        ->update(['producer_id' => $product->producer_id]);
                    DB::table('product_stock_inflow_schedules')
                        ->where('product_id', $product->id)
                        ->whereNull('producer_id')
                        ->update(['producer_id' => $product->producer_id]);
                }
            });
    }

    public function down(): void
    {
        // Data backfill; nothing sensible to reverse.
    }
};
