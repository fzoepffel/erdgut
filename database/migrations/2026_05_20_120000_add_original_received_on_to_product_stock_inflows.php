<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Track the originally-scheduled date for each inflow row.
 *
 * The materializer used to match scheduled occurrences by `received_on`.
 * That made it impossible to edit an inflow's date — the cron would
 * just re-create the original date the next time it ran, because
 * "no row exists for source_schedule_id=X received_on=originalDate".
 *
 * `original_received_on` decouples those two semantics:
 *   - `received_on` = the actual day stock arrives / is sellable
 *   - `original_received_on` = the day the schedule intended
 *
 * The materializer now matches on `original_received_on`. Admin-edited
 * dates set only `received_on`; `original_received_on` stays put so
 * the cron leaves the row alone.
 *
 * For one-off (non-schedule) inflows, `original_received_on` is set
 * equal to `received_on` for consistency.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->date('original_received_on')->nullable()->after('received_on');
        });

        // Backfill: every existing row's original date is its current date.
        DB::table('product_stock_inflows')
            ->whereNull('original_received_on')
            ->update(['original_received_on' => DB::raw('received_on')]);

        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->date('original_received_on')->nullable(false)->change();
            // Make scheduled-row dedup fast: the materializer's existence
            // check is `WHERE source_schedule_id = X AND original_received_on = Y`.
            $table->index(['source_schedule_id', 'original_received_on'], 'inflows_schedule_origin_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->dropIndex('inflows_schedule_origin_idx');
            $table->dropColumn('original_received_on');
        });
    }
};
