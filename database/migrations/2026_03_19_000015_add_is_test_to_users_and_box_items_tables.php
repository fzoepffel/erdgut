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
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('profile_preferences');
            $table->index('is_test');
        });

        Schema::table('box_items', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('is_extra');
            $table->index('is_test');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });

        Schema::table('box_items', function (Blueprint $table): void {
            $table->dropIndex(['is_test']);
            $table->dropColumn('is_test');
        });
    }
};
