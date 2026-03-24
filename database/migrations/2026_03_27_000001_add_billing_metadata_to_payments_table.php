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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('box_id')->nullable()->after('subscription_id')->constrained('boxes')->nullOnDelete();
            $table->date('fulfillment_week_start')->nullable()->after('box_id');
            $table->string('charge_initiator', 32)->default('scheduled')->after('fulfillment_week_start');
            $table->foreignId('initiated_by_user_id')->nullable()->after('charge_initiator')->constrained('users')->nullOnDelete();

            $table->index(['subscription_id', 'fulfillment_week_start', 'status'], 'payments_sub_fulfillment_status_index');
            $table->index('charge_initiator', 'payments_charge_initiator_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_sub_fulfillment_status_index');
            $table->dropIndex('payments_charge_initiator_index');
            $table->dropConstrainedForeignId('initiated_by_user_id');
            $table->dropColumn('charge_initiator');
            $table->dropColumn('fulfillment_week_start');
            $table->dropConstrainedForeignId('box_id');
        });
    }
};
