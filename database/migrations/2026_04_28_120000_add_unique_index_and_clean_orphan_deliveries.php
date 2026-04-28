<?php

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First: clean every legacy orphan canonical delivery that survived
        // before the validation tightened. Without this, the unique index
        // below could fail (multiple orphan rows for the same user+date),
        // and once it succeeds we're left with rows that don't tie to any
        // box or order anyway.
        Delivery::query()
            ->where('status', DeliveryStatus::Scheduled)
            ->whereNull('box_id')
            ->whereNull('order_id')
            ->where('is_weekly_override', false)
            ->delete();

        Schema::table('deliveries', function (Blueprint $table): void {
            // One canonical delivery per user+day. Overrides for the same
            // user+day are allowed alongside (they have is_weekly_override=true)
            // so the index includes the override flag in its tuple.
            $table->unique(
                ['user_id', 'scheduled_for_date', 'is_weekly_override'],
                'deliveries_user_date_override_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropUnique('deliveries_user_date_override_unique');
        });
    }
};
