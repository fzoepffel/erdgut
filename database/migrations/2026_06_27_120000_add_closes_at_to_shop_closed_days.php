<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-day closing time. A ShopClosedDay row with `closes_at` set means
     * the day stays open until that moment, then closes — a one-off cutoff
     * that overrides any recurring rule for that date. NULL keeps the prior
     * meaning: closed immediately ("close now").
     */
    public function up(): void
    {
        Schema::table('shop_closed_days', function (Blueprint $table) {
            $table->timestamp('closes_at')->nullable()->after('closed_on');
        });
    }

    public function down(): void
    {
        Schema::table('shop_closed_days', function (Blueprint $table) {
            $table->dropColumn('closes_at');
        });
    }
};
