<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D6 / D7 / F8 (UX-review notes): align product.category with the
 * marketing-home Kapitel taxonomy.
 *
 *   vegetables / fruit  →  produce
 *   dairy               →  eggs_and_dairy
 *   bakery              →  bread
 *   fish / meat         →  unchanged
 *
 * This is a string column rewrite — there's no DB-level enum
 * constraint to alter (Laravel's `string` casting + the PHP enum
 * `App\Enums\ProductCategory` are the only validators). After this
 * migration runs, any catalog row carrying an old value will have
 * been mapped forward; rolling back is best-effort (vegetable /
 * fruit distinction is lost when collapsed into `produce`).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->whereIn('category', ['vegetables', 'fruit'])
            ->update(['category' => 'produce']);

        DB::table('products')->where('category', 'dairy')
            ->update(['category' => 'eggs_and_dairy']);

        DB::table('products')->where('category', 'bakery')
            ->update(['category' => 'bread']);
    }

    public function down(): void
    {
        // Best-effort rollback: produce → vegetables (we can't tell
        // which rows were originally fruit; rolling back a merge is
        // lossy by definition).
        DB::table('products')->where('category', 'produce')
            ->update(['category' => 'vegetables']);

        DB::table('products')->where('category', 'eggs_and_dairy')
            ->update(['category' => 'dairy']);

        DB::table('products')->where('category', 'bread')
            ->update(['category' => 'bakery']);
    }
};
