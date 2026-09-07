<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bilingual mercurial: each recipient has a persisted language (de/en) so the
 * admin can send a German or English version per customer, and each sent
 * issue records both language versions.
 *
 * - mercurial_recipients.locale: language for a hand-added contact.
 * - restaurant_profiles.mercurial_locale: mercurial-specific language override
 *   for a restaurant account (defaults to the account locale when null; does
 *   NOT change the account/shop language).
 * - mercurials.subject_en / body_html_en: the English version of a sent issue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mercurial_recipients', function (Blueprint $table): void {
            $table->string('locale', 5)->nullable()->after('restaurant');
        });

        Schema::table('restaurant_profiles', function (Blueprint $table): void {
            $table->string('mercurial_locale', 5)->nullable()->after('contact_name');
        });

        Schema::table('mercurials', function (Blueprint $table): void {
            $table->string('subject_en')->nullable()->after('subject');
            $table->longText('body_html_en')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('mercurial_recipients', function (Blueprint $table): void {
            $table->dropColumn('locale');
        });

        Schema::table('restaurant_profiles', function (Blueprint $table): void {
            $table->dropColumn('mercurial_locale');
        });

        Schema::table('mercurials', function (Blueprint $table): void {
            $table->dropColumn(['subject_en', 'body_html_en']);
        });
    }
};
