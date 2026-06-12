<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A prior deploy attempt may have created the table before the FK failed
        // (MySQL identifier length limit). Drop and recreate with short names.
        Schema::dropIfExists('shop_delivery_closure_exceptions');

        Schema::create('shop_delivery_closure_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('shop_delivery_closure_id');
            $table->date('date');
            $table->timestamps();

            $table->foreign('shop_delivery_closure_id', 'sd_cl_exc_closure_fk')
                ->references('id')
                ->on('shop_delivery_closures')
                ->cascadeOnDelete();

            $table->unique(['shop_delivery_closure_id', 'date'], 'sd_cl_exc_closure_date_uq');
            $table->index('date', 'sd_cl_exc_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_delivery_closure_exceptions');
    }
};
