<?php

use App\Enums\TasteProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-form, admin-authored taste tags that aren't part of the
     * {@see TasteProfile} enum. Stored as a list of localized
     * objects — each entry carries a de/en/fr label so a custom tag shows
     * in the visitor's language, exactly like the enum-backed tags do via
     * the translation files. The enum tags themselves stay in
     * `taste_profile_tags`.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('custom_taste_tags')->nullable()->after('taste_profile_tags');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('custom_taste_tags');
        });
    }
};
