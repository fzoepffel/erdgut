<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('box_id')
                ->nullable()
                ->after('order_id')
                ->constrained('boxes')
                ->nullOnDelete();
            $table->index('box_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropForeign(['box_id']);
            $table->dropIndex(['box_id']);
            $table->dropColumn('box_id');
        });
    }

    /**
     * Link each delivery to its box:
     * - Trial deliveries (order_id set): the box the order owns.
     * - Subscription deliveries: the box for (user_id, week = ISO Monday of scheduled_for_date).
     */
    private function backfill(): void
    {
        DB::table('deliveries')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->chunkById(200, function ($deliveries) {
                foreach ($deliveries as $delivery) {
                    $order = DB::table('orders')->where('id', $delivery->order_id)->first();
                    if ($order === null || $order->box_id === null) {
                        continue;
                    }
                    DB::table('deliveries')->where('id', $delivery->id)->update(['box_id' => $order->box_id]);
                }
            });

        DB::table('deliveries')
            ->whereNull('order_id')
            ->whereNotNull('scheduled_for_date')
            ->orderBy('id')
            ->chunkById(200, function ($deliveries) {
                foreach ($deliveries as $delivery) {
                    $weekStart = Carbon::parse($delivery->scheduled_for_date)
                        ->startOfWeek()
                        ->toDateString();

                    $box = DB::table('boxes')
                        ->where('user_id', $delivery->user_id)
                        ->whereDate('week_start_date', $weekStart)
                        ->whereNull('order_id')
                        ->first(['id']);

                    if ($box !== null) {
                        DB::table('deliveries')->where('id', $delivery->id)->update(['box_id' => $box->id]);
                    }
                }
            });
    }
};
