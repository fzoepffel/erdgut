<?php

namespace Tests\Feature\Database;

use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardDataEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_eager_loaded_dashboard_graph_traversal_executes_no_extra_queries(): void
    {
        $user = User::factory()->create();
        $this->seedDashboardGraph($user, 2, 2, 2, 2);

        $dashboardUser = User::query()
            ->with([
                'addresses',
                'primaryAddress',
                'subscriptions.payments',
                'payments',
                'boxes.items.product.translations',
                'boxes.items.product.producer.translations',
                'boxes.items.product.producer',
                'boxes.products',
                'boxes.products.translations',
                'deliveries.address',
            ])
            ->findOrFail($user->id);

        $queryCount = $this->countQueriesDuring(function () use ($dashboardUser): void {
            $this->traverseDashboardGraph($dashboardUser);
        });

        $this->assertSame(0, $queryCount);
    }

    public function test_dashboard_eager_loading_query_count_remains_bounded_as_data_grows(): void
    {
        $smallUser = User::factory()->create();
        $largeUser = User::factory()->create();

        $this->seedDashboardGraph($smallUser, 1, 1, 1, 1);
        $this->seedDashboardGraph($largeUser, 4, 5, 4, 4);

        $smallQueryCount = $this->countQueriesDuring(function () use ($smallUser): void {
            $this->loadAndTraverseDashboardGraph($smallUser->id);
        });
        $largeQueryCount = $this->countQueriesDuring(function () use ($largeUser): void {
            $this->loadAndTraverseDashboardGraph($largeUser->id);
        });

        $this->assertGreaterThan(0, $smallQueryCount);
        $this->assertLessThanOrEqual($smallQueryCount + 2, $largeQueryCount);
    }

    private function loadAndTraverseDashboardGraph(int $userId): void
    {
        $dashboardUser = User::query()
            ->with([
                'addresses',
                'primaryAddress',
                'subscriptions.payments',
                'payments',
                'boxes.items.product.translations',
                'boxes.items.product.producer.translations',
                'boxes.items.product.producer',
                'boxes.products',
                'boxes.products.translations',
                'deliveries.address',
            ])
            ->findOrFail($userId);

        $this->traverseDashboardGraph($dashboardUser);
    }

    private function traverseDashboardGraph(User $user): void
    {
        $user->addresses->each(fn (Address $address): bool => $address->is_primary);
        $user->primaryAddress?->id;

        $user->subscriptions->each(function (Subscription $subscription): void {
            $subscription->payments->each(fn (Payment $payment): string => $payment->status);
        });

        $user->payments->each(fn (Payment $payment): string => $payment->currency);

        $user->boxes->each(function (Box $box): void {
            $box->items->each(function (BoxItem $item): void {
                $item->product->producer?->name;
                $item->product?->name;
            });

            $box->products->each(fn (Product $product): string => $product->name);
        });

        $user->deliveries->each(function (Delivery $delivery): void {
            $delivery->address?->street_line_1;
        });
    }

    private function seedDashboardGraph(
        User $user,
        int $subscriptionCount,
        int $paymentsPerSubscription,
        int $boxCount,
        int $deliveriesCount
    ): void {
        $primaryAddress = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        Address::factory()->create(['user_id' => $user->id]);

        $products = Product::factory()->count(max($boxCount * 2, 2))->create();

        for ($i = 0; $i < $subscriptionCount; $i++) {
            $subscription = Subscription::factory()->create(['user_id' => $user->id]);

            for ($attempt = 1; $attempt <= $paymentsPerSubscription; $attempt++) {
                Payment::factory()->create([
                    'subscription_id' => $subscription->id,
                    'attempt_number' => $attempt,
                ]);
            }
        }

        for ($i = 0; $i < $boxCount; $i++) {
            $box = Box::factory()->create(['user_id' => $user->id]);

            $productSlice = $products->slice($i, 2);
            foreach ($productSlice as $product) {
                BoxItem::factory()->create([
                    'box_id' => $box->id,
                    'product_id' => $product->id,
                ]);
            }
        }

        for ($i = 0; $i < $deliveriesCount; $i++) {
            // Pin scheduled_for_date per iteration so we never collide with
            // the unique (user_id, scheduled_for_date, is_weekly_override)
            // index. The factory's random date picker can otherwise produce
            // two deliveries on the same day for the same user.
            Delivery::factory()->create([
                'user_id' => $user->id,
                'address_id' => $primaryAddress->id,
                'scheduled_for_date' => now()->addDays($i + 1)->toDateString(),
            ]);
        }
    }

    private function countQueriesDuring(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        DB::flushQueryLog();

        return count($queries);
    }
}
