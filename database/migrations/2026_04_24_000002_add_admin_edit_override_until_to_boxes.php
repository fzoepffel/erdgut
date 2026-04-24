<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->timestamp('admin_edit_override_until')->nullable()->after('modification_deadline_at');
        });
    }

    public function down(): void
    {
        Schema::table('boxes', function (Blueprint $table): void {
            $table->dropColumn('admin_edit_override_until');
        });
    }
};
