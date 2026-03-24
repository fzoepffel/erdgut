<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminBoxDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    private function mondayWeek(): Carbon
    {
        return Carbon::today()->next(Carbon::MONDAY)->startOfDay();
    }

    public function test_guest_redirected_from_distribute(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08'));

        $week = $this->mondayWeek()->toDateString();

        $this->get(route('app.admin.boxes.distribute', ['week_start_date' => $week]))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_forbidden_on_get_and_post(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08'));

        $user = User::factory()->create(['is_admin' => false]);
        $week = $this->mondayWeek()->toDateString();

        $this->actingAs($user)
            ->get(route('app.admin.boxes.distribute', ['week_start_date' => $week]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.boxes.distribute.apply'), [
                'week_start_date' => $week,
                'confirm_distribute' => '1',
            ])
            ->assertForbidden();
    }

    public function test_admin_preview_ok_and_post_balances_products_across_boxes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08'));

        Config::set('box.composition_by_size.small', [
            'vegetables' => 2,
        ]);

        Product::factory()->create([
            'name' => 'Dist Veg Alpha',
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
        ]);
        Product::factory()->create([
            'name' => 'Dist Veg Beta',
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
        ]);

        $u1 = User::factory()->create();
        $u2 = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $u1->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);
        Subscription::factory()->create([
            'user_id' => $u2->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $week = $this->mondayWeek();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.distribute', ['week_start_date' => $week->toDateString()]))
            ->assertOk();

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.distribute.apply'), [
                'week_start_date' => $week->toDateString(),
                'confirm_distribute' => '1',
            ])
            ->assertRedirect(route('app.admin.boxes.planner'))
            ->assertSessionHas('status');

        $alphaId = Product::query()->where('name', 'Dist Veg Alpha')->value('id');
        $betaId = Product::query()->where('name', 'Dist Veg Beta')->value('id');

        $alphaLines = BoxItem::query()
            ->where('is_extra', false)
            ->whereIn('box_id', Box::query()->forWeek($week->toDateString())->pluck('id'))
            ->where('product_id', $alphaId)
            ->count();

        $betaLines = BoxItem::query()
            ->where('is_extra', false)
            ->whereIn('box_id', Box::query()->forWeek($week->toDateString())->pluck('id'))
            ->where('product_id', $betaId)
            ->count();

        $this->assertSame(2, $alphaLines);
        $this->assertSame(2, $betaLines);
    }

    public function test_apply_preserves_shop_extras(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08'));

        Config::set('box.composition_by_size.small', [
            'vegetables' => 1,
        ]);

        $veg = Product::factory()->create([
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
        ]);
        $extraProduct = Product::factory()->create([
            'category' => 'dairy',
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);

        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $week = $this->mondayWeek();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.store'), [
                'subscription_id' => Subscription::query()->where('user_id', $user->id)->first()->id,
                'week_start_date' => $week->toDateString(),
            ])
            ->assertRedirect();

        $box = Box::forUser($user->id)->forWeek($week->toDateString())->first();
        $this->assertNotNull($box);

        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $extraProduct->id,
            'quantity' => 1,
            'unit' => 'piece',
            'is_extra' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.distribute.apply'), [
                'week_start_date' => $week->toDateString(),
                'confirm_distribute' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(1, $box->items()->where('is_extra', true)->count());
        $this->assertSame(1, $box->items()->where('is_extra', false)->count());
        $this->assertSame($veg->id, (int) $box->items()->where('is_extra', false)->value('product_id'));
    }

    public function test_locked_box_skipped_other_boxes_updated(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08'));

        Config::set('box.composition_by_size.small', [
            'vegetables' => 1,
        ]);

        Product::factory()->create([
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
        ]);

        $editableUser = User::factory()->create();
        $lockedUser = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $editableUser->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);
        Subscription::factory()->create([
            'user_id' => $lockedUser->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $week = $this->mondayWeek();
        $admin = User::factory()->admin()->create();

        foreach ([$editableUser, $lockedUser] as $customer) {
            $sub = Subscription::query()->where('user_id', $customer->id)->first();
            $this->actingAs($admin)
                ->post(route('app.admin.boxes.store'), [
                    'subscription_id' => $sub->id,
                    'week_start_date' => $week->toDateString(),
                ])
                ->assertRedirect();
        }

        $lockedBox = Box::forUser($lockedUser->id)->forWeek($week->toDateString())->first();
        $lockedBox->lock();
        BoxItem::factory()->create([
            'box_id' => $lockedBox->id,
            'product_id' => Product::factory()->create([
                'category' => 'vegetables',
                'is_available' => true,
                'is_published' => true,
            ])->id,
            'quantity' => 1,
            'unit' => 'piece',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('app.admin.boxes.distribute.apply'), [
                'week_start_date' => $week->toDateString(),
                'confirm_distribute' => '1',
            ]);

        $response->assertRedirect(route('app.admin.boxes.planner'));
        $response->assertSessionHas('status');

        $editableBox = Box::forUser($editableUser->id)->forWeek($week->toDateString())->first();
        $this->assertSame(1, $editableBox->items()->where('is_extra', false)->count());

        $lockedBox->refresh();
        $this->assertSame(1, $lockedBox->items()->where('is_extra', false)->count());
    }

    public function test_preview_html_does_not_leak_sumup_strings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'dist_leak_token_secret',
            'sumup_customer_id' => 'dist_cust_secret',
        ]);

        $week = $this->mondayWeek()->toDateString();

        $html = $this->actingAs($admin)
            ->get(route('app.admin.boxes.distribute', ['week_start_date' => $week]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('dist_leak_token_secret', $html);
        $this->assertStringNotContainsString('dist_cust_secret', $html);
        $this->assertStringNotContainsString('sumup_token', $html);
    }
}
