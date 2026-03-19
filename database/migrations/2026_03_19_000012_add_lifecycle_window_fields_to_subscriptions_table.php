<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->date('next_charge_date')->nullable()->after('status');
            $table->date('pause_from')->nullable()->after('next_charge_date');
            $table->date('pause_until')->nullable()->after('pause_from');
            $table->timestamp('cancelled_at')->nullable()->after('pause_until');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'next_charge_date',
                'pause_from',
                'pause_until',
                'cancelled_at',
                'cancellation_reason',
            ]);
        });
    }
};
