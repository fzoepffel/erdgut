<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ERD-337: admin refund action.
 *
 * Adds book-keeping columns so admins can refund a paid payment. The payment
 * stays linked to its original box/subscription/order — a refund is a status
 * transition plus metadata, not a separate record. Provider-side API call is
 * optional (config('payments.refund_api_enabled')).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('refunded_at')->nullable()->after('manual_marked_by_user_id');
            $table->decimal('refund_amount', 10, 2)->nullable()->after('refunded_at');
            $table->string('refund_reason', 500)->nullable()->after('refund_amount');
            $table->string('refund_reference', 160)->nullable()->after('refund_reason');
            $table->foreignId('refunded_by_user_id')->nullable()->after('refund_reference')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('refunded_by_user_id');
            $table->dropColumn(['refunded_at', 'refund_amount', 'refund_reason', 'refund_reference']);
        });
    }
};
