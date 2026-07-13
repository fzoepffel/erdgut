<?php

use App\Http\Controllers\Admin\ProducerController;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * products.producer_id becomes the nullable PRIMARY producer (mirror of the
 * lowest-position product_producer row). Deleting a producer no longer
 * cascades into deleting its products; the FK nulls out and
 * {@see ProducerController::destroy()} promotes
 * the next pivot producer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['producer_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('producer_id')->nullable()->change();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('producer_id')->references('id')->on('producers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['producer_id']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('producer_id')->nullable(false)->change();
        });
        Schema::table('products', function (Blueprint $table) {
            $table->foreign('producer_id')->references('id')->on('producers')->cascadeOnDelete();
        });
    }
};
