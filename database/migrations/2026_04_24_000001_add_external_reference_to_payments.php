<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('external_reference', 160)->nullable()->after('provider_payload');
            $table->timestamp('manual_marked_at')->nullable()->after('external_reference');
            $table->foreignId('manual_marked_by_user_id')->nullable()->after('manual_marked_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('manual_marked_by_user_id');
            $table->dropColumn(['manual_marked_at', 'external_reference']);
        });
    }
};
