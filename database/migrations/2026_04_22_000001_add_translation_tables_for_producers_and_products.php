<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('producer_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('region')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['producer_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        Schema::create('product_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('unit', 64);
            $table->string('origin', 128);
            $table->string('variety', 128);
            $table->text('taste_profile');
            $table->timestamps();

            $table->unique(['product_id', 'locale']);
            $table->index(['locale', 'name']);
        });

        // Backfill from current producer columns (treat existing values as DE source and copy to EN/FR).
        DB::table('producers')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $now = now();
                $insert = [];
                foreach ($rows as $p) {
                    foreach (['de', 'en', 'fr'] as $locale) {
                        $insert[] = [
                            'producer_id' => $p->id,
                            'locale' => $locale,
                            'name' => $p->name,
                            'region' => $p->region,
                            'description' => $p->description,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($insert !== []) {
                    DB::table('producer_translations')->insert($insert);
                }
            }, 'id');

        // Backfill from current product name + metadata (treat existing values as DE source and copy to EN/FR).
        DB::table('products')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $now = now();
                $insert = [];
                foreach ($rows as $p) {
                    $meta = [];
                    if (is_string($p->metadata)) {
                        $decoded = json_decode($p->metadata, true);
                        if (is_array($decoded)) {
                            $meta = $decoded;
                        }
                    } elseif (is_array($p->metadata)) {
                        $meta = $p->metadata;
                    }

                    $unit = (string) ($meta['unit'] ?? '');
                    $origin = (string) ($meta['origin'] ?? '');
                    $variety = (string) ($meta['variety'] ?? '');
                    $taste = (string) ($meta['taste_profile'] ?? '');

                    foreach (['de', 'en', 'fr'] as $locale) {
                        $insert[] = [
                            'product_id' => $p->id,
                            'locale' => $locale,
                            'name' => $p->name,
                            'unit' => $unit,
                            'origin' => $origin,
                            'variety' => $variety,
                            'taste_profile' => $taste,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($insert !== []) {
                    DB::table('product_translations')->insert($insert);
                }
            }, 'id');

        // Remove translatable keys from products.metadata (keep metadata for technical/non-translatable keys).
        DB::table('products')
            ->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $meta = [];
                    if (is_string($row->metadata)) {
                        $decoded = json_decode($row->metadata, true);
                        if (is_array($decoded)) {
                            $meta = $decoded;
                        }
                    } elseif (is_array($row->metadata)) {
                        $meta = $row->metadata;
                    }

                    if ($meta === []) {
                        continue;
                    }

                    unset($meta['unit'], $meta['origin'], $meta['variety'], $meta['taste_profile']);
                    DB::table('products')->where('id', $row->id)->update([
                        'metadata' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]);
                }
            }, 'id');

        // Drop old columns after backfill.
        Schema::table('producers', function (Blueprint $table): void {
            $table->dropColumn(['name', 'region', 'description']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['name']);
        });
    }

    public function down(): void
    {
        // Restore old columns using DE translations as the source of truth.
        Schema::table('producers', function (Blueprint $table): void {
            $table->string('name');
            $table->string('region')->nullable();
            $table->text('description')->nullable();
        });

        DB::table('producers')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $t = DB::table('producer_translations')
                    ->where('producer_id', $row->id)
                    ->where('locale', 'de')
                    ->first();

                DB::table('producers')->where('id', $row->id)->update([
                    'name' => $t?->name ?? '',
                    'region' => $t?->region,
                    'description' => $t?->description,
                ]);
            }
        }, 'id');

        Schema::table('products', function (Blueprint $table): void {
            $table->string('name');
        });

        DB::table('products')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $t = DB::table('product_translations')
                    ->where('product_id', $row->id)
                    ->where('locale', 'de')
                    ->first();

                $meta = [];
                if (is_string($row->metadata)) {
                    $decoded = json_decode($row->metadata, true);
                    if (is_array($decoded)) {
                        $meta = $decoded;
                    }
                } elseif (is_array($row->metadata)) {
                    $meta = $row->metadata;
                }

                if ($t !== null) {
                    $meta['unit'] = $t->unit;
                    $meta['origin'] = $t->origin;
                    $meta['variety'] = $t->variety;
                    $meta['taste_profile'] = $t->taste_profile;
                }

                DB::table('products')->where('id', $row->id)->update([
                    'name' => $t?->name ?? '',
                    'metadata' => $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }
        }, 'id');

        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('producer_translations');
    }
};
