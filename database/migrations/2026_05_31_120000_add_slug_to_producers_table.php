<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds a URL-safe slug to producers so the public producer detail page can
 * be addressed as /producers/{slug}. Backfills existing rows from the
 * German translation name (the canonical, user-facing default), falling
 * back to en/fr, then to producer-{id} if no name exists at all. Slugs are
 * deduped so the unique index never collides on identically-named farms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producers', function (Blueprint $table): void {
            $table->string('slug')->nullable()->unique()->after('id');
        });

        $used = [];
        $rows = DB::table('producers')->orderBy('id')->pluck('id');

        foreach ($rows as $id) {
            // Pick the name by locale preference in PHP so the backfill works
            // identically on MySQL (prod) and SQLite (tests).
            $names = DB::table('producer_translations')
                ->where('producer_id', $id)
                ->pluck('name', 'locale');

            $name = $names['de'] ?? $names['en'] ?? $names['fr'] ?? $names->first();

            $base = Str::slug((string) $name) ?: 'producer-'.$id;
            $slug = $base;
            $n = 2;
            while (in_array($slug, $used, true)) {
                $slug = $base.'-'.$n;
                $n++;
            }
            $used[] = $slug;

            DB::table('producers')->where('id', $id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('producers', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
