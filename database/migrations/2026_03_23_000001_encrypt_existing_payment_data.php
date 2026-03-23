<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Encrypt existing plaintext payment-related columns before encrypted casts take effect.
     * Run once when deploying ERD-113. Safe to run on empty tables.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->longText('provider_payload')->nullable()->change();
        });

        $this->encryptSubscriptions();
        $this->encryptPayments();
    }

    public function down(): void
    {
        // Decryption would require APP_KEY and is irreversible for security.
        // Do not revert; re-deploy previous version if rollback needed.
    }

    private function encryptSubscriptions(): void
    {
        foreach (DB::table('subscriptions')->whereNotNull('sumup_token')->get() as $row) {
            if ($this->isPlaintext($row->sumup_token)) {
                DB::table('subscriptions')->where('id', $row->id)->update([
                    'sumup_token' => Crypt::encryptString($row->sumup_token),
                ]);
            }
        }

        foreach (DB::table('subscriptions')->whereNotNull('sumup_customer_id')->get() as $row) {
            if ($this->isPlaintext($row->sumup_customer_id)) {
                DB::table('subscriptions')->where('id', $row->id)->update([
                    'sumup_customer_id' => Crypt::encryptString($row->sumup_customer_id),
                ]);
            }
        }
    }

    private function encryptPayments(): void
    {
        foreach (DB::table('payments')->whereNotNull('provider_payload')->get() as $row) {
            $raw = $row->provider_payload;
            $json = is_string($raw) ? $raw : json_encode($raw);
            if ($this->isPlaintext($json)) {
                DB::table('payments')->where('id', $row->id)->update([
                    'provider_payload' => Crypt::encryptString($json),
                ]);
            }
        }
    }

    private function isPlaintext(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return false;
        } catch (Throwable) {
            return true;
        }
    }
};
