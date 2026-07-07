<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pairings are reciprocal ("A pairs well with B" implies the reverse), and
 * the admin form now maintains both directions. Backfill: insert the missing
 * inverse row for every existing one-directional pairing, copying the note
 * (it describes the pairing, not a direction).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $missing = DB::table('product_pairings as p')
            ->leftJoin('product_pairings as r', function ($join): void {
                $join->on('r.product_id', '=', 'p.paired_product_id')
                    ->on('r.paired_product_id', '=', 'p.product_id');
            })
            ->whereNull('r.id')
            ->whereColumn('p.product_id', '!=', 'p.paired_product_id')
            ->select('p.product_id', 'p.paired_product_id', 'p.note')
            ->get();

        foreach ($missing->chunk(200) as $chunk) {
            DB::table('product_pairings')->insertOrIgnore(
                $chunk->map(fn ($row): array => [
                    'product_id' => (int) $row->paired_product_id,
                    'paired_product_id' => (int) $row->product_id,
                    'note' => $row->note,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all(),
            );
        }
    }

    public function down(): void
    {
        // Irreversible by design: we cannot tell which direction was original.
    }
};
