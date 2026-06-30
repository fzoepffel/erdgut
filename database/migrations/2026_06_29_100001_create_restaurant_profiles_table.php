<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Business details + verification state for a restaurant (B2B) account.
     * One row per restaurant user. Billing address + USt-IdNr are collected
     * up front for the later B2B invoice (R6).
     */
    public function up(): void
    {
        Schema::create('restaurant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('business_name', 160);
            $table->string('contact_name', 120);
            $table->string('handelsregisternummer', 60)->nullable();
            $table->string('ust_id_nr', 40)->nullable();
            $table->string('billing_street', 160);
            $table->string('billing_postal_code', 16);
            $table->string('billing_city', 120);
            $table->string('verification_status', 20)->default('pending')->index();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_profiles');
    }
};
