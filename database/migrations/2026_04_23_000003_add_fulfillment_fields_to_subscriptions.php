<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('address_id')
                ->nullable()
                ->after('user_id')
                ->constrained('addresses')
                ->nullOnDelete();
            $table->unsignedTinyInteger('delivery_weekday')
                ->nullable()
                ->after('address_id');
            $table->string('preferred_timeslot', 32)
                ->nullable()
                ->after('delivery_weekday');
            $table->string('fulfillment_mode', 16)
                ->default('delivery')
                ->after('preferred_timeslot');

            $table->index('delivery_weekday');
            $table->index('fulfillment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropIndex(['delivery_weekday']);
            $table->dropIndex(['fulfillment_mode']);
            $table->dropColumn(['address_id', 'delivery_weekday', 'preferred_timeslot', 'fulfillment_mode']);
        });
    }
};
