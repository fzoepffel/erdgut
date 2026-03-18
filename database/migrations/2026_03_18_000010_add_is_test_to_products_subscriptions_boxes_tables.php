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
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('metadata');
            $table->index('is_test');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('sumup_customer_id');
            $table->index('is_test');
        });

        Schema::table('boxes', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('estimated_value');
            $table->index('is_test');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });

        Schema::table('boxes', function (Blueprint $table): void {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });
    }
};
