<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fine-tune pass after first browser preview:
 *
 * - Customers think in grams for fresh produce, not kilograms — switch
 *   `quantity_kg` (decimal kg) to `quantity_grams` (int g). Any existing
 *   rows (none in production yet, but harmless if some staging dev
 *   played with the form) are converted by quantity_grams = kg * 1000.
 *
 * - Surface the expected delivery Monday on the row itself so the
 *   admin's manual fulfilment list doesn't have to recompute the
 *   Thursday-cutoff date. Populated at order-create time by
 *   SpargelOrderService::nextDeliveryMonday().
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add new columns nullable first so backfill can complete before
        // we tighten quantity_grams to NOT NULL.
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_grams')->after('asparagus_type')->nullable();
            $table->date('expected_delivery_date')->after('total_amount_eur')->nullable();
        });

        // Backfill from any pre-existing rows. Pre-launch this is empty in
        // production but a dev DB might hold a few play orders.
        DB::table('spargel_orders')
            ->whereNotNull('quantity_kg')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('spargel_orders')
                    ->where('id', $row->id)
                    ->update([
                        'quantity_grams' => (int) round(((float) $row->quantity_kg) * 1000),
                    ]);
            });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn('quantity_kg');
        });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_grams')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->decimal('quantity_kg', 5, 2)->after('asparagus_type')->nullable();
        });

        DB::table('spargel_orders')
            ->whereNotNull('quantity_grams')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('spargel_orders')
                    ->where('id', $row->id)
                    ->update([
                        'quantity_kg' => round(((int) $row->quantity_grams) / 1000, 2),
                    ]);
            });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn(['quantity_grams', 'expected_delivery_date']);
            $table->decimal('quantity_kg', 5, 2)->nullable(false)->change();
        });
    }
};
