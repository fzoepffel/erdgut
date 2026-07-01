<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-line order model: `line_status` lets in-stock lines be confirmed instantly
 * while on-request/special lines wait for the admin, and `recurrence` /
 * `recurrence_until` make the order self-describing (so the checkout summary and
 * order views can show which lines repeat, instead of that intent living only on
 * the standing order).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_order_items', 'line_status')) {
                $table->string('line_status', 20)->default('confirmed')->after('track');
            }
            if (! Schema::hasColumn('restaurant_order_items', 'recurrence')) {
                $table->string('recurrence', 20)->default('one_time')->after('line_status');
            }
            if (! Schema::hasColumn('restaurant_order_items', 'recurrence_until')) {
                $table->date('recurrence_until')->nullable()->after('recurrence');
            }
        });

        // Backfill line_status for existing rows from their track + order status.
        // Reserved (in-stock) lines are always confirmed.
        DB::table('restaurant_order_items')->where('track', 'reserved')->update(['line_status' => 'confirmed']);

        // Requested lines: confirmed if the order was already accepted historically
        // (confirmed/fulfilled), else still requested (submitted orders).
        DB::table('restaurant_order_items')
            ->where('track', 'requested')
            ->whereIn('restaurant_order_id', fn ($q) => $q->select('id')->from('restaurant_orders')->whereIn('status', ['confirmed', 'fulfilled']))
            ->update(['line_status' => 'confirmed']);
        DB::table('restaurant_order_items')
            ->where('track', 'requested')
            ->whereIn('restaurant_order_id', fn ($q) => $q->select('id')->from('restaurant_orders')->where('status', 'submitted'))
            ->update(['line_status' => 'requested']);

        // Declined orders → their lines are declined.
        DB::table('restaurant_order_items')
            ->whereIn('restaurant_order_id', fn ($q) => $q->select('id')->from('restaurant_orders')->where('status', 'declined'))
            ->update(['line_status' => 'declined']);
    }

    public function down(): void
    {
        Schema::table('restaurant_order_items', function (Blueprint $table): void {
            $table->dropColumn(['line_status', 'recurrence', 'recurrence_until']);
        });
    }
};
