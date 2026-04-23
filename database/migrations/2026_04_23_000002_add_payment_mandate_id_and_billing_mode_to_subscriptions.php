<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('payment_mandate_id')
                ->nullable()
                ->after('user_id')
                ->constrained('payment_mandates')
                ->nullOnDelete();
            $table->string('billing_mode', 32)
                ->default('sumup_recurring')
                ->after('status');

            $table->index('billing_mode');
        });

        $this->backfillMandates();
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['payment_mandate_id']);
            $table->dropIndex(['billing_mode']);
            $table->dropColumn(['payment_mandate_id', 'billing_mode']);
        });
    }

    /**
     * For each user with a sumup_token on any subscription, create one PaymentMandate
     * (using the most recent subscription's encrypted credentials) and link all of
     * that user's subscriptions to it.
     */
    private function backfillMandates(): void
    {
        $rows = DB::table('subscriptions')
            ->whereNotNull('sumup_token')
            ->whereNotNull('sumup_customer_id')
            ->orderBy('user_id')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'sumup_token', 'sumup_customer_id', 'created_at']);

        $seen = [];
        foreach ($rows as $row) {
            if (in_array($row->user_id, $seen, true)) {
                continue;
            }
            $seen[] = $row->user_id;

            $mandateId = DB::table('payment_mandates')->insertGetId([
                'user_id' => $row->user_id,
                'provider' => 'sumup',
                'provider_customer_id' => $row->sumup_customer_id,
                'provider_token' => $row->sumup_token,
                'status' => 'active',
                'created_at' => $row->created_at ?? now(),
                'updated_at' => now(),
            ]);

            DB::table('subscriptions')
                ->where('user_id', $row->user_id)
                ->whereNotNull('sumup_token')
                ->update(['payment_mandate_id' => $mandateId]);
        }
    }
};
