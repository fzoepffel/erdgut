<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_week_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->decimal('planned_quantity', 10, 2)->default(0);
            $table->string('unit', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'week_start_date']);
            $table->index('week_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_week_stocks');
    }
};
