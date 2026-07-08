<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Time-bound request-area overrides: `request_visibility` 'always'/'never'
 * may expire on a date, after which the product falls back to 'auto' (the
 * season window). Null = the override holds indefinitely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'request_visibility_until')) {
                $table->date('request_visibility_until')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('request_visibility_until');
        });
    }
};
