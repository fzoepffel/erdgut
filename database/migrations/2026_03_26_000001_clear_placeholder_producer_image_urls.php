<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Remove dev-only placeholder host from producers; avoids broken requests on production.
     */
    public function up(): void
    {
        DB::table('producers')
            ->where('image_url', 'like', '%images.erdgut.local%')
            ->update(['image_url' => null]);
    }

    /**
     * Irreversible: we do not restore placeholder URLs.
     */
    public function down(): void
    {
        //
    }
};
