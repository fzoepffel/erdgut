<?php

namespace Tests\Feature\Onboarding;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\BoxPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Pricing-consistency guard for W2-16 item 3 — every customer-facing
 * surface that shows a recurring price must show the same number for
 * the same `(size, eggs)` choice. The acceptance criterion the
 * walkthrough doc spelled out.
 *
 * Walks every (size × eggs) combo and asserts the calculator's
 * `subscriptionPerDelivery` matches:
 *   - `Subscription::expectedChargeAmount()` (Charge command + admin views)
 *   - the dashboard subscription panel HTML
 *   - the `/subscriptions/show` HTML
 *
 * The onboarding wizard's `/payment` step is covered by the same
 * `expectedChargeAmount` path (the Blade calls it directly).
 */
class PricingConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('box.included_value_by_size', [
            'small' => 20.0,
            'medium' => 35.0,
            'large' => 42.0,
        ]);
        Config::set('box.eggs_dairy_add_on_eur', 4.5);
    }

    /**
     * @return list<array{size: string, eggs: bool, expected: float}>
     */
    public static function priceCombinations(): array
    {
        return [
            ['size' => 'small', 'eggs' => false, 'expected' => 20.00],
            ['size' => 'small', 'eggs' => true, 'expected' => 24.50],
            ['size' => 'medium', 'eggs' => false, 'expected' => 35.00],
            ['size' => 'medium', 'eggs' => true, 'expected' => 39.50],
            ['size' => 'large', 'eggs' => false, 'expected' => 42.00],
            ['size' => 'large', 'eggs' => true, 'expected' => 46.50],
        ];
    }

    /**
     * @dataProvider priceCombinations
     */
    public function test_every_size_eggs_combo_renders_the_same_price_everywhere(string $size, bool $eggs, float $expected): void
    {
        // Calculator is the source of truth.
        $calc = app(BoxPriceCalculator::class);
        $this->assertSame($expected, $calc->subscriptionPerDelivery($size, $eggs));

        // Subscription model resolves to the same number.
        // Status is pinned to PendingPayment with no SumUp token so the
        // dashboard deterministically renders the resume-setup panel — that
        // panel is the surface the dashboard uses to show the price today.
        // (Subscription::factory() defaults to Active + optional token, which
        // randomly lands in either the resume-setup branch or the active
        // branch and made this test flaky across combos.)
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'box_size' => $size,
            'status' => SubscriptionStatus::PendingPayment,
            'sumup_token' => null,
            'preferences' => [
                'veg_only' => true,
                'meat_add_on' => false,
                'eggs_dairy_add_on' => $eggs,
            ],
        ]);
        $this->assertSame($expected, $subscription->expectedChargeAmount());

        // Dashboard renders the same number on the subscription panel.
        $formatted = number_format($expected, 2, ',', '.');
        $dashboardHtml = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($formatted, $dashboardHtml);

        // /subscriptions/show renders the same number too.
        $subShowHtml = $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString($formatted, $subShowHtml);
    }
}
