<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('user_id')->constrained('orders')->nullOnDelete();
            $table->index('order_id', 'boxes_order_id_index');
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('address_id')->constrained('orders')->nullOnDelete();
            $table->index('order_id', 'deliveries_order_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropIndex('deliveries_order_id_index');
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('boxes', function (Blueprint $table) {
            $table->dropIndex('boxes_order_id_index');
            $table->dropConstrainedForeignId('order_id');
        });
    }
};
