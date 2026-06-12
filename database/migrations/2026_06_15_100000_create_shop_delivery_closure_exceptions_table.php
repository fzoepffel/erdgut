<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_delivery_closure_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_delivery_closure_id')
                ->constrained('shop_delivery_closures')
                ->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['shop_delivery_closure_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_delivery_closure_exceptions');
    }
};
