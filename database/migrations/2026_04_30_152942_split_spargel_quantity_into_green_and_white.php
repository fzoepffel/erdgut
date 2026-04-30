<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trial-shop schema change — customers can now order green AND white
 * asparagus in the same order, with independent amounts per variety.
 *
 * Old shape: one row had `asparagus_type` ('green'|'white') + a single
 * `quantity_grams` (int).
 *
 * New shape: one row has `quantity_green_grams` + `quantity_white_grams`,
 * either of which may be 0 — but the combined total must be at least
 * QUANTITY_COMBINED_MIN (enforced in the FormRequest, not the schema).
 *
 * Existing rows are migrated by routing the old `quantity_grams` value
 * into whichever per-variety column matches the old `asparagus_type`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_green_grams')->after('asparagus_type')->nullable()->default(0);
            $table->unsignedInteger('quantity_white_grams')->after('quantity_green_grams')->nullable()->default(0);
        });

        // Backfill from any pre-existing rows.
        DB::table('spargel_orders')
            ->whereNotNull('quantity_grams')
            ->orderBy('id')
            ->each(function (object $row): void {
                $type = $row->asparagus_type ?? 'green';
                $grams = (int) ($row->quantity_grams ?? 0);

                DB::table('spargel_orders')
                    ->where('id', $row->id)
                    ->update([
                        'quantity_green_grams' => $type === 'green' ? $grams : 0,
                        'quantity_white_grams' => $type === 'white' ? $grams : 0,
                    ]);
            });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn(['quantity_grams', 'asparagus_type']);
        });

        // Tighten new columns to NOT NULL after backfill.
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_green_grams')->nullable(false)->default(0)->change();
            $table->unsignedInteger('quantity_white_grams')->nullable(false)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->enum('asparagus_type', ['green', 'white'])->after('phone')->nullable();
            $table->unsignedInteger('quantity_grams')->after('asparagus_type')->nullable();
        });

        DB::table('spargel_orders')
            ->orderBy('id')
            ->each(function (object $row): void {
                $green = (int) ($row->quantity_green_grams ?? 0);
                $white = (int) ($row->quantity_white_grams ?? 0);

                // If both > 0 we can only pick one for the rollback —
                // prefer the larger one. Acceptable because the old
                // shape can't represent both.
                $type = $green >= $white ? 'green' : 'white';
                $grams = $type === 'green' ? $green : $white;

                DB::table('spargel_orders')
                    ->where('id', $row->id)
                    ->update([
                        'asparagus_type' => $grams > 0 ? $type : 'green',
                        'quantity_grams' => $grams,
                    ]);
            });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropColumn(['quantity_green_grams', 'quantity_white_grams']);
        });

        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->unsignedInteger('quantity_grams')->nullable(false)->change();
        });
    }
};
