<?php

namespace App\Services\Admin;

use App\Enums\ShopStockAudience;
use App\Models\Producer;
use App\Models\Product;
use App\Support\InventoryCache;
use App\Support\ProducerLabels;
use Illuminate\Support\Carbon;

/**
 * Computes which FARM covers which share of the committed demand — the
 * read-model behind farmer exports, farm totals, and packing labels.
 *
 * Rules (Fred's consolidation rule made deterministic):
 *  - Per day, producers are ranked by their ANCHOR SCORE: the number of
 *    (product, grade) demands for which they are the ONLY candidate that
 *    day (deliveries they must make no matter what). Ties break by the
 *    count of distinct (product, grade) they can supply, then producer id.
 *  - Allocation exhausts the top-ranked producer's batches (FEFO within
 *    the producer) before touching the next — big per-farm orders, nobody
 *    delivers a single bunch of parsley.
 *  - Batch remainders persist across days (a batch consumed on Monday is
 *    gone on Tuesday), mirroring the availability allocator's window.
 *  - FALLBACK: demand not covered by any batch is attributed to the
 *    product's PRIMARY producer (products.producer_id). In the current
 *    single-producer state this reproduces the legacy attribution exactly
 *    (see ProducerAttributionParityTest). Products without a primary fall
 *    into key 0 ("no producer").
 *
 * Attribution is advisory: it never feeds availability. Across multi-day
 * freshness windows it may deplete batches in a different order than the
 * pure-FEFO availability math — cosmetic at farm scale.
 */
class ProducerAllocationService
{
    private const BUFFER_DAYS = 60; // mirror InventoryService::BATCH_OVERLAP_BUFFER_DAYS

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Producer shares of the demand on the given dates.
     *
     * @param  list<int>  $productIds
     * @param  list<string>  $isoDates
     * @param  array<int, array<int, array<string, float>>>|null  $injectedDemand  [pid][vkey][iso => qty]; null = global commitments
     * @return array<string, array<int, array<int, array<int, float>>>> [iso][pid][vkey][producerId => qty] (producerId 0 = unattributable)
     */
    public function allocate(array $productIds, array $isoDates, ?array $injectedDemand = null): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        sort($productIds);
        $isoDates = array_values(array_unique($isoDates));
        sort($isoDates);
        if ($productIds === [] || $isoDates === []) {
            return [];
        }

        if ($injectedDemand === null) {
            $suffix = 'palloc.global.'.md5(implode(',', $productIds).'|'.implode(',', $isoDates));

            return InventoryCache::remember($suffix, fn (): array => $this->compute($productIds, $isoDates, null)['allocation']);
        }

        // Injected demand comes from a caller-specific order set (e.g. the
        // farmer export's filter); not cacheable by inputs we can name.
        return $this->compute($productIds, $isoDates, $injectedDemand)['allocation'];
    }

    /**
     * Which producers still have SELLABLE stock on the given dates — the
     * customer-facing "who supplies this on my delivery day" read-model.
     * End-state semantics: committed demand on ANY day has already drawn
     * the batches down, mirroring how availability treats multi-day
     * freshness windows. b2b_only batches deplete either way but only
     * count towards the BusinessTrade view.
     *
     * Advisory like the allocation itself: it can diverge from the
     * availability math at the margin (ranking vs pure FEFO, dual-pool
     * B2B), so callers must fall back to the primary producer when a
     * product is missing from the result.
     *
     * @param  list<int>  $productIds
     * @param  list<string>  $isoDates
     * @return array<string, array<int, array<int, float>>> [iso][pid][producerId => remainingQty], qty desc
     */
    public function remainingByProducer(array $productIds, array $isoDates, ShopStockAudience $audience = ShopStockAudience::Consumer): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        sort($productIds);
        $isoDates = array_values(array_unique($isoDates));
        sort($isoDates);
        if ($productIds === [] || $isoDates === []) {
            return [];
        }

        $suffix = 'palloc.remaining.'.$audience->value.'.'.md5(implode(',', $productIds).'|'.implode(',', $isoDates));

        return InventoryCache::remember($suffix, function () use ($productIds, $isoDates, $audience): array {
            $pools = $this->compute($productIds, $isoDates, null)['remaining'];

            $out = [];
            foreach ($pools as $iso => $byPid) {
                foreach ($byPid as $pid => $byProducer) {
                    $shares = [];
                    foreach ($byProducer as $producerId => $pool) {
                        $qty = $pool['shared'] + ($audience === ShopStockAudience::BusinessTrade ? $pool['b2b'] : 0.0);
                        if ($qty > 1e-9) {
                            $shares[$producerId] = round($qty, 4);
                        }
                    }
                    if ($shares !== []) {
                        uksort($shares, fn (int $a, int $b): int => [$shares[$b], $a] <=> [$shares[$a], $b]);
                        $out[$iso][$pid] = $shares;
                    }
                }
            }

            return $out;
        });
    }

    /**
     * Producer names per order line ("pid|vkey", vkey 0 = default grade)
     * for ONE delivery date — the label on receipts, invoices and order
     * status pages. Names only (allocated share desc), joined/collapsed
     * via ProducerLabels. Lines without a computed share are absent;
     * callers fall back to the product's primary producer.
     *
     * @param  list<int>  $productIds
     * @return array<string, string>
     */
    public function namesByItemKey(array $productIds, Carbon $date): array
    {
        $out = [];
        foreach ($this->summaryForDate($productIds, $date) as $pid => $byVkey) {
            foreach ($byVkey as $vkey => $rows) {
                $names = array_values(array_filter(array_column($rows, 'name')));
                $label = ProducerLabels::label($names);
                if ($label !== '') {
                    $out[$pid.'|'.$vkey] = $label;
                }
            }
        }

        return $out;
    }

    /**
     * Display helper for ONE date: producer shares with names, primary
     * first, ordered by quantity desc.
     *
     * @param  list<int>  $productIds
     * @param  array<int, array<int, array<string, float>>>|null  $injectedDemand
     * @return array<int, array<int, list<array{producerId: int, name: string, qty: float}>>> [pid][vkey] => shares
     */
    public function summaryForDate(array $productIds, Carbon $date, ?array $injectedDemand = null): array
    {
        $iso = $date->copy()->startOfDay()->toDateString();
        $allocation = $this->allocate($productIds, [$iso], $injectedDemand)[$iso] ?? [];

        $producerIds = [];
        foreach ($allocation as $byVkey) {
            foreach ($byVkey as $shares) {
                foreach (array_keys($shares) as $producerId) {
                    if ($producerId > 0) {
                        $producerIds[$producerId] = true;
                    }
                }
            }
        }
        $names = Producer::query()->with('translations')
            ->whereIn('id', array_keys($producerIds))
            ->get()
            ->mapWithKeys(fn (Producer $p): array => [$p->id => (string) $p->name]);

        $out = [];
        foreach ($allocation as $pid => $byVkey) {
            foreach ($byVkey as $vkey => $shares) {
                $rows = [];
                foreach ($shares as $producerId => $qty) {
                    if ($qty <= 1e-9) {
                        continue;
                    }
                    $rows[] = [
                        'producerId' => (int) $producerId,
                        'name' => $producerId > 0 ? (string) ($names[$producerId] ?? '') : '',
                        'qty' => round($qty, 4),
                    ];
                }
                usort($rows, fn (array $a, array $b): int => [$b['qty'], $a['producerId']] <=> [$a['qty'], $b['producerId']]);
                $out[$pid][$vkey] = $rows;
            }
        }

        return $out;
    }

    /**
     * @param  list<int>  $productIds
     * @param  list<string>  $isoDates  sorted
     * @param  array<int, array<int, array<string, float>>>|null  $injectedDemand
     * @return array{allocation: array<string, array<int, array<int, array<int, float>>>>, remaining: array<string, array<int, array<int, array{shared: float, b2b: float}>>>}
     */
    private function compute(array $productIds, array $isoDates, ?array $injectedDemand): array
    {
        $from = Carbon::parse($isoDates[0])->startOfDay();
        $to = Carbon::parse($isoDates[count($isoDates) - 1])->startOfDay();

        $batchesByPid = $this->inventory->batchesForAllocation(
            $productIds,
            $from->copy()->subDays(self::BUFFER_DAYS),
            $to->copy()->addDays(self::BUFFER_DAYS),
        );

        // Demand span must cover every loaded batch window so consumption
        // outside [from, to] still depletes the shared batches.
        $spanFrom = $from->copy();
        $spanTo = $to->copy();
        foreach ($batchesByPid as $byVkey) {
            foreach ($byVkey as $batches) {
                foreach ($batches as $b) {
                    if ($b['start']->lt($spanFrom)) {
                        $spanFrom = $b['start']->copy();
                    }
                    if ($b['end']->gt($spanTo)) {
                        $spanTo = $b['end']->copy();
                    }
                }
            }
        }

        $demand = $injectedDemand ?? $this->inventory->committedForAllocation($productIds, $spanFrom, $spanTo);

        $primaryByPid = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('producer_id', 'id')
            ->map(fn ($v): int => (int) ($v ?? 0))
            ->all();

        // Mutable batch state: [pid][vkey] => list of {producer, end, start, remaining}.
        $state = [];
        foreach ($batchesByPid as $pid => $byVkey) {
            foreach ($byVkey as $vkey => $batches) {
                foreach ($batches as $b) {
                    $state[$pid][$vkey][] = [
                        'producer_id' => (int) ($b['producer_id'] ?? 0) ?: ($primaryByPid[$pid] ?? 0),
                        'start' => $b['start'],
                        'end' => $b['end'],
                        'remaining' => (float) $b['qty'],
                        'b2b_only' => (bool) ($b['b2b_only'] ?? false),
                    ];
                }
            }
        }

        // All demand days across all products, chronological.
        $allDays = [];
        foreach ($demand as $byVkey) {
            foreach ($byVkey as $byIso) {
                foreach ($byIso as $iso => $qty) {
                    if ((float) $qty > 1e-9) {
                        $allDays[$iso] = true;
                    }
                }
            }
        }
        $allDays = array_keys($allDays);
        sort($allDays);

        $wanted = array_flip($isoDates);
        $out = [];

        foreach ($allDays as $iso) {
            $day = Carbon::parse($iso)->startOfDay();

            // Candidates per (pid, vkey) and per-producer stats for ranking.
            $candidates = [];  // pid => vkey => list<producerId>
            $anchor = [];      // producerId => sole-candidate count
            $breadth = [];     // producerId => distinct (pid,vkey) supplied
            foreach ($demand as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $byIso) {
                    if ((float) ($byIso[$iso] ?? 0) <= 1e-9) {
                        continue;
                    }
                    $live = [];
                    foreach ($state[$pid][$vkey] ?? [] as $b) {
                        if ($b['remaining'] > 1e-9 && $b['start']->lte($day) && $b['end']->gte($day) && $b['producer_id'] > 0) {
                            $live[$b['producer_id']] = true;
                        }
                    }
                    $live = array_keys($live);
                    $candidates[$pid][$vkey] = $live;
                    foreach ($live as $producerId) {
                        $breadth[$producerId] = ($breadth[$producerId] ?? 0) + 1;
                    }
                    if (count($live) === 1) {
                        $anchor[$live[0]] = ($anchor[$live[0]] ?? 0) + 1;
                    }
                }
            }

            $rank = function (int $producerId) use ($anchor, $breadth): array {
                return [-($anchor[$producerId] ?? 0), -($breadth[$producerId] ?? 0), $producerId];
            };

            foreach ($demand as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $byIso) {
                    $need = (float) ($byIso[$iso] ?? 0);
                    if ($need <= 1e-9) {
                        continue;
                    }

                    // Batches live today, ordered by (producer rank, FEFO).
                    $indexes = [];
                    foreach ($state[$pid][$vkey] ?? [] as $i => $b) {
                        if ($b['remaining'] > 1e-9 && $b['start']->lte($day) && $b['end']->gte($day)) {
                            $indexes[] = $i;
                        }
                    }
                    usort($indexes, function (int $a, int $b) use ($state, $pid, $vkey, $rank): int {
                        $ba = $state[$pid][$vkey][$a];
                        $bb = $state[$pid][$vkey][$b];

                        return [$rank($ba['producer_id']), $ba['end']->getTimestamp(), $ba['start']->getTimestamp(), $a]
                            <=> [$rank($bb['producer_id']), $bb['end']->getTimestamp(), $bb['start']->getTimestamp(), $b];
                    });

                    foreach ($indexes as $i) {
                        if ($need <= 1e-9) {
                            break;
                        }
                        $take = min($need, $state[$pid][$vkey][$i]['remaining']);
                        $state[$pid][$vkey][$i]['remaining'] -= $take;
                        $need -= $take;
                        if (isset($wanted[$iso])) {
                            $producerId = $state[$pid][$vkey][$i]['producer_id'];
                            $out[$iso][$pid][$vkey][$producerId] = ($out[$iso][$pid][$vkey][$producerId] ?? 0.0) + $take;
                        }
                    }

                    // Uncovered remainder → the product's primary producer.
                    if ($need > 1e-9 && isset($wanted[$iso])) {
                        $primary = $primaryByPid[$pid] ?? 0;
                        $out[$iso][$pid][$vkey][$primary] = ($out[$iso][$pid][$vkey][$primary] ?? 0.0) + $need;
                    }
                }
            }
        }

        // Round for stable comparisons.
        foreach ($out as $iso => $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    foreach ($shares as $producerId => $qty) {
                        $out[$iso][$pid][$vkey][$producerId] = round($qty, 4);
                    }
                }
            }
        }

        // End-state batch remainders per producer for the requested days,
        // split by pool (shared vs b2b_only) so remainingByProducer() can
        // apply the audience view without a second compute.
        $remaining = [];
        foreach ($isoDates as $iso) {
            $day = Carbon::parse($iso)->startOfDay();
            foreach ($state as $pid => $byVkey) {
                foreach ($byVkey as $batches) {
                    foreach ($batches as $b) {
                        if ($b['remaining'] <= 1e-9 || $b['producer_id'] <= 0 || $b['start']->gt($day) || $b['end']->lt($day)) {
                            continue;
                        }
                        $pool = &$remaining[$iso][$pid][$b['producer_id']];
                        $pool ??= ['shared' => 0.0, 'b2b' => 0.0];
                        $pool[$b['b2b_only'] ? 'b2b' : 'shared'] += $b['remaining'];
                        unset($pool);
                    }
                }
            }
        }

        return ['allocation' => $out, 'remaining' => $remaining];
    }
}
