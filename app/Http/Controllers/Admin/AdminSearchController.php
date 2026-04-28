<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Producer;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Cmd+K admin quick-jump.
 *
 * One JSON endpoint that searches the most-used admin entities at once
 * so the palette can render grouped results without N round-trips.
 *
 * The contract is intentionally narrow: 5 results per category, fixed
 * shape ({id, label, sublabel, url}). The view is responsible for
 * rendering and keyboard navigation.
 */
class AdminSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $q = trim((string) ($data['q'] ?? ''));

        if ($q === '') {
            return response()->json($this->emptyShape());
        }

        return response()->json([
            'users' => $this->searchUsers($q),
            'subscriptions' => $this->searchSubscriptions($q),
            'deliveries' => $this->searchDeliveries($q),
            'producers' => $this->searchProducers($q),
            'products' => $this->searchProducts($q),
        ]);
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function emptyShape(): array
    {
        return [
            'users' => [],
            'subscriptions' => [],
            'deliveries' => [],
            'producers' => [],
            'products' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchUsers(string $q): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return User::query()
            ->where(function ($w) use ($like): void {
                $w->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like);
            })
            ->orderBy('email')
            ->limit(5)
            ->get(['id', 'name', 'email', 'is_admin'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'label' => $u->email,
                'sublabel' => trim((string) $u->name) !== '' ? $u->name : null,
                'badge' => $u->is_admin ? 'admin' : null,
                'url' => route('app.admin.users.index', ['q' => $u->email]),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchSubscriptions(string $q): array
    {
        $query = Subscription::query()
            ->select(['subscriptions.id', 'subscriptions.user_id', 'subscriptions.status', 'subscriptions.plan'])
            ->with('user:id,email');

        if (ctype_digit($q)) {
            $query->where('subscriptions.id', (int) $q);
        } else {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->whereHas('user', fn ($w) => $w->where('email', 'like', $like));
        }

        return $query->orderByDesc('subscriptions.id')
            ->limit(5)
            ->get()
            ->map(fn (Subscription $s) => [
                'id' => $s->id,
                'label' => '#'.$s->id,
                'sublabel' => $s->user?->email,
                'badge' => $s->status?->value,
                'url' => route('app.admin.subscriptions.index', ['q' => $s->user?->email ?? '']),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchDeliveries(string $q): array
    {
        $query = Delivery::query()
            ->select(['deliveries.id', 'deliveries.user_id', 'deliveries.scheduled_for_date', 'deliveries.status'])
            ->with('user:id,email');

        if (ctype_digit($q)) {
            $query->where('deliveries.id', (int) $q);
        } elseif ($this->looksLikeDate($q)) {
            $date = $this->normalizeDate($q);
            if ($date !== null) {
                $query->whereDate('deliveries.scheduled_for_date', $date);
            }
        } else {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->whereHas('user', fn ($w) => $w->where('email', 'like', $like));
        }

        return $query->orderByDesc('deliveries.scheduled_for_date')
            ->limit(5)
            ->get()
            ->map(fn (Delivery $d) => [
                'id' => $d->id,
                'label' => $d->scheduled_for_date?->isoFormat('D. MMM YYYY') ?? '#'.$d->id,
                'sublabel' => $d->user?->email,
                'badge' => $d->status?->value,
                'url' => route('app.admin.deliveries.edit', $d),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchProducers(string $q): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return Producer::query()
            ->whereHas('translations', fn ($w) => $w->where('name', 'like', $like))
            ->with('translations:id,producer_id,locale,name')
            ->limit(5)
            ->get()
            ->map(function (Producer $p) {
                $name = $p->translationFor()?->name ?? '';

                return [
                    'id' => $p->id,
                    'label' => $name,
                    'sublabel' => null,
                    'badge' => null,
                    'url' => route('app.admin.producers.edit', $p),
                ];
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function searchProducts(string $q): array
    {
        $like = '%'.addcslashes($q, '%_\\').'%';

        return Product::query()
            ->whereHas('translations', fn ($w) => $w->where('name', 'like', $like))
            ->with(['translations:id,product_id,locale,name', 'producer:id'])
            ->limit(5)
            ->get()
            ->map(function (Product $p) {
                $name = $p->translationFor()?->name ?? '';
                $producerName = $p->producer?->translationFor()?->name;

                return [
                    'id' => $p->id,
                    'label' => $name,
                    'sublabel' => $producerName,
                    'badge' => $p->category,
                    'url' => route('app.admin.products.edit', $p),
                ];
            })
            ->all();
    }

    private function looksLikeDate(string $q): bool
    {
        // Accepts 2026-04-28, 28.04.2026, 28.4.26.
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $q) === 1
            || preg_match('/^\d{1,2}\.\d{1,2}\.\d{2,4}$/', $q) === 1;
    }

    private function normalizeDate(string $q): ?string
    {
        try {
            if (str_contains($q, '-')) {
                return Carbon::createFromFormat('Y-m-d', $q)->toDateString();
            }
            $parts = explode('.', $q);
            if (count($parts) !== 3) {
                return null;
            }
            [$d, $m, $y] = $parts;
            $y = (int) $y;
            if ($y < 100) {
                $y += 2000;
            }

            return Carbon::create((int) $y, (int) $m, (int) $d)?->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
