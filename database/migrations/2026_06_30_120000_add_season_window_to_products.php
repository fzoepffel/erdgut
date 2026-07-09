<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recurring yearly season window per product.
 *
 * Products already carry an `is_seasonal` flag; these columns give it real
 * dates: a start and end (month + day) that repeat every year (e.g. asparagus
 * 1 Apr – 24 Jun). A window may wrap the year boundary (e.g. Nov – Feb). The
 * window is only meaningful when `is_seasonal` is true; a seasonal product
 * with no window is treated as in season year-round.
 *
 * Used by the restaurant (B2B) shop to surface in-season products even when
 * they are temporarily out of stock. The consumer storefront is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedTinyInteger('season_start_month')->nullable()->after('is_seasonal');
            $table->unsignedTinyInteger('season_start_day')->nullable()->after('season_start_month');
            $table->unsignedTinyInteger('season_end_month')->nullable()->after('season_start_day');
            $table->unsignedTinyInteger('season_end_day')->nullable()->after('season_end_month');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'season_start_month',
                'season_start_day',
                'season_end_month',
                'season_end_day',
            ]);
        });
    }
};
