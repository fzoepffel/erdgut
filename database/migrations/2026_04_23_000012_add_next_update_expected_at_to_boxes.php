<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->timestamp('next_update_expected_at')->nullable()->after('modification_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->dropColumn('next_update_expected_at');
        });
    }
};
