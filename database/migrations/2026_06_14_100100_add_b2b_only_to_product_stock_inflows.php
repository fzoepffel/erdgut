<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->boolean('b2b_only')->default(false)->after('freshness_window_days');
        });
    }

    public function down(): void
    {
        Schema::table('product_stock_inflows', function (Blueprint $table): void {
            $table->dropColumn('b2b_only');
        });
    }
};
