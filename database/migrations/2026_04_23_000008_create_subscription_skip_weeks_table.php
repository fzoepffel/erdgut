<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_skip_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->date('week_start_date');
            $table->string('source', 16)->default('user');
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'week_start_date']);
            $table->index('week_start_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_skip_weeks');
    }
};
