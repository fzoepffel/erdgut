<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Shop\ProductRankingService;
use Illuminate\Console\Command;

/**
 * Prints the current decayed engagement + value behind each product's storefront
 * ranking, so operators can see *why* products rank where they do. Read-only.
 *
 * Shows the posterior *mean* add-to-cart rate (not a random Thompson draw) so
 * the table is stable run-to-run; the live storefront samples around this mean.
 */
class ShopRankingReport extends Command
{
    protected $signature = 'shop:ranking-report {--limit=50 : Max products to list}';

    protected $description = 'Show decayed impressions, add-to-carts, value and mean score behind the shop ranking';

    public function handle(ProductRankingService $ranking): int
    {
        $priorAlpha = (float) config('shop.ranking.prior_alpha', 1.0);
        $priorBeta = (float) config('shop.ranking.prior_beta', 20.0);

        $stats = $ranking->engagementStats();
        $products = Product::query()->visibleInCatalog()->with('translations')->get()->keyBy('id');

        $rows = $products->map(function (Product $p) use ($stats, $priorAlpha, $priorBeta): array {
            $s = $stats->get($p->id, ['impressions' => 0.0, 'add_to_carts' => 0.0, 'purchase_value' => 0.0]);
            $impr = (float) $s['impressions'];
            $carts = (float) $s['add_to_carts'];
            $alpha = $carts + $priorAlpha;
            $beta = max(0.0, $impr - $carts) + $priorBeta;
            $meanRate = $alpha / ($alpha + $beta);
            $value = $carts > 0 ? $s['purchase_value'] / $carts : $p->rankingPriorValue();

            return [
                'name' => $p->name,
                'impr' => round($impr, 1),
                'carts' => round($carts, 1),
                'rate' => round($meanRate * 100, 2).'%',
                'value' => number_format($value, 2).' €',
                'score' => round($meanRate * $value, 4),
            ];
        })->sortByDesc('score')->take((int) $this->option('limit'))->values();

        $this->table(
            ['Product', 'Impr.', 'Carts', 'Cart rate', 'Value/cart', 'Mean score'],
            $rows->map(fn ($r) => array_values($r))->all(),
        );

        $this->newLine();
        $this->info(sprintf(
            'Objective: %s · half-life: %d days · priors α=%.1f β=%.1f',
            config('shop.ranking.objective', 'margin'),
            (int) config('shop.ranking.half_life_days', 28),
            $priorAlpha,
            $priorBeta,
        ));

        return self::SUCCESS;
    }
}
