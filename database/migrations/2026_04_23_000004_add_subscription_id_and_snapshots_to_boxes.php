<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('subscription_id')
                ->nullable()
                ->after('order_id')
                ->constrained('subscriptions')
                ->nullOnDelete();
            $table->json('preferences_snapshot')->nullable()->after('estimated_value');
            $table->json('allergies_snapshot')->nullable()->after('preferences_snapshot');
            $table->string('box_size_snapshot', 16)->nullable()->after('allergies_snapshot');
            $table->string('fulfillment_mode_snapshot', 16)->nullable()->after('box_size_snapshot');

            $table->index('subscription_id');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
            $table->dropIndex(['subscription_id']);
            $table->dropColumn([
                'subscription_id',
                'preferences_snapshot',
                'allergies_snapshot',
                'box_size_snapshot',
                'fulfillment_mode_snapshot',
            ]);
        });
    }

    /**
     * Backfill:
     * - Subscription boxes (order_id null): link subscription_id by user + pick the user's
     *   latest subscription; copy preferences/allergies/box_size from it.
     * - Trial boxes (order_id set): copy preferences/allergies/box_size from the order.
     * - fulfillment_mode_snapshot defaults to 'delivery' for all historical rows.
     */
    private function backfill(): void
    {
        DB::table('boxes')
            ->whereNull('order_id')
            ->orderBy('id')
            ->chunkById(200, function ($boxes) {
                foreach ($boxes as $box) {
                    $sub = DB::table('subscriptions')
                        ->where('user_id', $box->user_id)
                        ->orderByDesc('id')
                        ->first();

                    if ($sub === null) {
                        continue;
                    }

                    DB::table('boxes')->where('id', $box->id)->update([
                        'subscription_id' => $sub->id,
                        'preferences_snapshot' => $sub->preferences,
                        'allergies_snapshot' => $sub->allergies,
                        'box_size_snapshot' => $sub->box_size,
                        'fulfillment_mode_snapshot' => 'delivery',
                    ]);
                }
            });

        DB::table('boxes')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->chunkById(200, function ($boxes) {
                foreach ($boxes as $box) {
                    $order = DB::table('orders')->where('id', $box->order_id)->first();
                    if ($order === null) {
                        continue;
                    }

                    DB::table('boxes')->where('id', $box->id)->update([
                        'preferences_snapshot' => $order->preferences,
                        'allergies_snapshot' => $order->allergies,
                        'box_size_snapshot' => $order->box_size,
                        'fulfillment_mode_snapshot' => 'delivery',
                    ]);
                }
            });
    }
};
