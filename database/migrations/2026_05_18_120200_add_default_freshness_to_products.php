<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product default freshness window in days.
 *
 * Used as the default `freshness_window_days` for newly created
 * inflows and schedules. Each inflow / schedule keeps its own column
 * so admin can override per receipt without changing the product
 * default. Defaults to 1 day (same-day-only) — the user's described
 * fallback while there's no cold storage on the farm.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedTinyInteger('default_freshness_window_days')
                ->default(1)
                ->after('price_display_unit');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('default_freshness_window_days');
        });
    }
};
