<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product override for the B2B Anfragen area: 'auto' follows the season
 * window (default, current behavior), 'always' features the product there
 * even out of season/stock (sourceable gap-fillers like artichokes),
 * 'never' hides an in-season product from the request list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'request_visibility')) {
                $table->string('request_visibility', 10)->default('auto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('request_visibility');
        });
    }
};
