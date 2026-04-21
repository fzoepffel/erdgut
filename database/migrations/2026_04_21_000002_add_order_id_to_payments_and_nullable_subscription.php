<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('subscription_id')->constrained('orders')->nullOnDelete();
            $table->index(['order_id', 'status'], 'payments_order_status_index');
        });

        // Make subscription_id nullable so a payment can belong to an order instead.
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex('payments_order_status_index');
            $table->dropConstrainedForeignId('order_id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
        });
    }
};
