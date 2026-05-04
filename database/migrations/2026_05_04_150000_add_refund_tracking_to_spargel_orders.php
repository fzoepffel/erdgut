<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spargel orders had no refund flow at all — the `Refunded` status enum
 * existed but nothing transitioned to it, no audit trail, no metadata.
 * These columns mirror the equivalent fields on the `payments` table
 * (refund_amount, refunded_at, refund_reason, refunded_by_user_id) so
 * the admin can refund a spargel order through SumUp from our UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->decimal('refund_amount', 8, 2)->nullable()->after('paid_at');
            $table->timestamp('refunded_at')->nullable()->after('refund_amount');
            $table->string('refund_reason', 500)->nullable()->after('refunded_at');
            $table->foreignId('refunded_by_user_id')->nullable()->after('refund_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('spargel_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refunded_by_user_id');
            $table->dropColumn(['refund_amount', 'refunded_at', 'refund_reason']);
        });
    }
};
