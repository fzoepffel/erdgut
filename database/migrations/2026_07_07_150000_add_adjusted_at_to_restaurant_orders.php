<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks orders whose lines were manually adjusted (owner or admin) so that
 * standing-order template changes never overwrite a hand-tuned week.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('restaurant_orders', 'adjusted_at')) {
                $table->timestamp('adjusted_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurant_orders', function (Blueprint $table): void {
            $table->dropColumn('adjusted_at');
        });
    }
};
