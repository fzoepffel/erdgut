<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Step 2 of 3 for the unit-collapse rebuild.
 *
 *  - Copy each product's old `unit` value into `price_display_unit`
 *    (so the admin's previous choice survives the collapse).
 *  - For products previously stored at `kg` or `l`, convert the
 *    storage to the canonical small unit:
 *      · `unit_price /= 1000`  (was €/kg → now €/g)
 *      · every `product_week_stocks.planned_quantity` *= 1000
 *      · every `product_week_stock_schedules.planned_quantity` *= 1000
 *  - Rewrite `products.unit` to the new four-kind enum:
 *      g, kg → mass     ;     ml, l → volume     ;     piece/bunch unchanged.
 *
 * Idempotent: re-running on already-collapsed rows is a no-op because
 * the SELECT filters on the legacy values.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // 1. price_display_unit ← old unit (only where still null, so re-runs are safe).
            DB::table('products')
                ->whereNull('price_display_unit')
                ->whereIn('unit', ['g', 'kg', 'ml', 'l', 'piece', 'bunch'])
                ->update(['price_display_unit' => DB::raw('`unit`')]);

            // 2. Convert prices + quantities for kg / l products.
            $largeUnitProducts = DB::table('products')
                ->whereIn('unit', ['kg', 'l'])
                ->pluck('id');

            if ($largeUnitProducts->isNotEmpty()) {
                DB::table('products')
                    ->whereIn('id', $largeUnitProducts)
                    ->update(['unit_price' => DB::raw('unit_price / 1000')]);

                DB::table('product_week_stocks')
                    ->whereIn('product_id', $largeUnitProducts)
                    ->update(['planned_quantity' => DB::raw('planned_quantity * 1000')]);

                // The schedules table only exists on staging-and-up; guard for the
                // case where this migration runs before Phase 2 has shipped.
                if (DB::getSchemaBuilder()->hasTable('product_week_stock_schedules')) {
                    DB::table('product_week_stock_schedules')
                        ->whereIn('product_id', $largeUnitProducts)
                        ->update(['planned_quantity' => DB::raw('planned_quantity * 1000')]);
                }
            }

            // 3. Collapse the unit column to the four kinds.
            DB::table('products')->whereIn('unit', ['g', 'kg'])->update(['unit' => 'mass']);
            DB::table('products')->whereIn('unit', ['ml', 'l'])->update(['unit' => 'volume']);
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            // Restore old unit values from price_display_unit where possible.
            DB::table('products')
                ->where('unit', 'mass')
                ->whereIn('price_display_unit', ['g', 'kg'])
                ->update(['unit' => DB::raw('price_display_unit')]);

            DB::table('products')
                ->where('unit', 'volume')
                ->whereIn('price_display_unit', ['ml', 'l'])
                ->update(['unit' => DB::raw('price_display_unit')]);

            // Reverse the price/quantity scaling for the products that ended up as kg/l.
            $largeUnitProducts = DB::table('products')
                ->whereIn('unit', ['kg', 'l'])
                ->pluck('id');

            if ($largeUnitProducts->isNotEmpty()) {
                DB::table('products')
                    ->whereIn('id', $largeUnitProducts)
                    ->update(['unit_price' => DB::raw('unit_price * 1000')]);

                DB::table('product_week_stocks')
                    ->whereIn('product_id', $largeUnitProducts)
                    ->update(['planned_quantity' => DB::raw('planned_quantity / 1000')]);

                if (DB::getSchemaBuilder()->hasTable('product_week_stock_schedules')) {
                    DB::table('product_week_stock_schedules')
                        ->whereIn('product_id', $largeUnitProducts)
                        ->update(['planned_quantity' => DB::raw('planned_quantity / 1000')]);
                }
            }
        });
    }
};
