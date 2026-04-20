<?php

namespace Tests\Unit\Support;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SubscriptionSkipCleanupService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionSkipCleanupServiceTest extends TestCase
{
    use RefreshDatabase;

    private function setupSub(): array
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        return [$user, $subscription];
    }

    public function test_editable_box_in_window_is_deleted_and_items_removed(): void
    {
        [$user, $subscription] = $this->setupSub();

        $weekStart = CarbonImmutable::parse('2026-05-04'); // Monday
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $product = Product::factory()->create();
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $weekStart,
            $weekStart->endOfWeek()
        );

        $this->assertDatabaseMissing('boxes', ['id' => $box->id]);
        $this->assertDatabaseMissing('box_items', ['id' => $item->id]);
    }

    public function test_box_outside_window_is_untouched(): void
    {
        [$user, $subscription] = $this->setupSub();

        $inside = CarbonImmutable::parse('2026-05-04');
        $outsideBox = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $inside->addWeeks(3)->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $inside,
            $inside->endOfWeek()
        );

        $this->assertDatabaseHas('boxes', ['id' => $outsideBox->id]);
    }

    public function test_locked_box_with_future_deadline_is_deleted(): void
    {
        [$user, $subscription] = $this->setupSub();
        $weekStart = CarbonImmutable::parse('2026-05-04');

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->addDays(2),
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $weekStart,
            $weekStart->endOfWeek()
        );

        $this->assertDatabaseMissing('boxes', ['id' => $box->id]);
    }

    public function test_locked_box_with_past_deadline_is_kept(): void
    {
        [$user, $subscription] = $this->setupSub();
        $weekStart = CarbonImmutable::parse('2026-05-04');

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDays(1),
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $weekStart,
            $weekStart->endOfWeek()
        );

        $this->assertDatabaseHas('boxes', ['id' => $box->id]);
    }

    public function test_delivered_box_is_untouched(): void
    {
        [$user, $subscription] = $this->setupSub();
        $weekStart = CarbonImmutable::parse('2026-05-04');

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Delivered,
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $weekStart,
            $weekStart->endOfWeek()
        );

        $this->assertDatabaseHas('boxes', ['id' => $box->id]);
    }

    public function test_scheduled_delivery_in_window_is_deleted(): void
    {
        [$user, $subscription] = $this->setupSub();
        $weekStart = CarbonImmutable::parse('2026-05-04');

        $scheduled = Delivery::factory()->create([
            'user_id' => $user->id,
            'scheduled_for_date' => $weekStart->addDays(2)->toDateString(),
            'status' => DeliveryStatus::Scheduled,
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $weekStart,
            $weekStart->endOfWeek()
        );

        $this->assertDatabaseMissing('deliveries', ['id' => $scheduled->id]);
    }

    public function test_delivered_delivery_kept(): void
    {
        [$user, $subscription] = $this->setupSub();
        $weekStart = CarbonImmutable::parse('2026-05-04');

        $delivered = Delivery::factory()->create([
            'user_id' => $user->id,
            'scheduled_for_date' => $weekStart->addDays(2)->toDateString(),
            'status' => DeliveryStatus::Delivered,
        ]);

        app(SubscriptionSkipCleanupService::class)->cleanupWindow(
            $subscription,
            $weekStart,
            $weekStart->endOfWeek()
        );

        $this->assertDatabaseHas('deliveries', ['id' => $delivered->id]);
    }
}
