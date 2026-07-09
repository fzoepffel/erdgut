<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Split the B2B space into shop ORDERS (in-stock only, instantly confirmed)
 * and REQUESTS (season/over-stock/free-form, admin-confirmed) — same table,
 * a `kind` flag. Backfill: any historical row that carries a requested-track
 * line becomes a request; pure in-stock rows are orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_orders', 'kind')) {
                $table->string('kind', 20)->default('order')->after('status')->index();
            }
        });

        DB::table('restaurant_orders')
            ->whereIn('id', fn ($q) => $q->select('restaurant_order_id')->from('restaurant_order_items')->where('track', 'requested'))
            ->update(['kind' => 'request']);
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
