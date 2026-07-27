<?php

namespace App\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * LengthAwarePaginator whose last page comes from cluster packing, not
 * ceil(total / perPage) — families must not be split across pages.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends LengthAwarePaginator<TKey, TValue>
 */
final class InventoryClusterPaginator extends LengthAwarePaginator
{
    public function __construct(
        Collection|array $items,
        int $totalProducts,
        int $lastPage,
        int $currentPage,
        array $options = [],
    ) {
        $lastPage = max(1, $lastPage);
        $currentPage = min(max(1, $currentPage), $lastPage);
        // Dummy per-page so parent math stays coherent; lastPage() is overridden.
        $perPage = max(1, (int) ceil($totalProducts / $lastPage));

        parent::__construct($items, $totalProducts, $perPage, $currentPage, $options);

        $this->lastPage = $lastPage;
    }

    public function lastPage(): int
    {
        return (int) $this->lastPage;
    }
}
