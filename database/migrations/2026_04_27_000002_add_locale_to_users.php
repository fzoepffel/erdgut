<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `locale` column to `users` so we can persist the user's preferred
 * language across sessions and use it as the rendering locale for queued
 * emails (via the `HasLocalePreference` contract on the User model).
 *
 * Nullable so existing users fall back to `config('app.locale')` until
 * they pick a language. New signups get the locale captured from the
 * active session (Accept-Language → middleware → onboarding controller).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });
    }
};
