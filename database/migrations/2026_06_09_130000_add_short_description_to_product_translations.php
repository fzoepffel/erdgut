<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            // Short, card-friendly description. The existing `taste_profile`
            // column keeps the long/detailed description (rendered on the
            // product detail page). Nullable so existing rows survive the
            // migration unbroken — the admin form enforces it on save, and
            // the public card falls back gracefully when it's still empty.
            $table->string('short_description', 240)->nullable()->after('taste_profile');
        });
    }

    public function down(): void
    {
        Schema::table('product_translations', function (Blueprint $table) {
            $table->dropColumn('short_description');
        });
    }
};
