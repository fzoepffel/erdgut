<?php

namespace Tests\Feature\Auth;

use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Models\Address;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W2-20: when an unverified customer lands on /auth/email/verify after just
 * completing the wizard, the page must show what they signed up for —
 * subscription details, delivery address (or pickup location), first
 * delivery date — so the verify-email gate feels like the last optional
 * step before food shows up, not a roadblock.
 *
 * The OnboardingDraft is cleared on successful payment-setup completion
 * (PaymentStepController::storeComplete), so the summary block must read
 * from User → Subscription → Address, not from the draft.
 */
class VerifyEmailSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_email_renders_subscription_summary_for_delivery_user(): void
    {
        $user = User::factory()->unverified()->create();
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
            'street_line_1' => 'Kastanienallee 12',
            'postal_code' => '10435',
            'city' => 'Berlin',
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
            'fulfillment_mode' => FulfillmentMode::Delivery,
        ]);

        $response = $this->actingAs($user)->get(route('verification.notice'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('verify-email-confirmation', $html);
        $this->assertStringContainsString(__('Your delivery address'), $html);
        $this->assertStringContainsString('Kastanienallee 12', $html);
        $this->assertStringContainsString('10435 Berlin', $html);
        $this->assertStringContainsString(__('Every week'), $html);
        $this->assertStringNotContainsString(__('Pickup location'), $html);
    }

    public function test_verify_email_summary_shows_pickup_location_for_pickup_user(): void
    {
        $user = User::factory()->unverified()->create();
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
        ]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'fulfillment_mode' => FulfillmentMode::Pickup,
        ]);

        $response = $this->actingAs($user)->get(route('verification.notice'));
        $response->assertOk();

        $html = $response->getContent();

        $this->assertStringContainsString('verify-email-confirmation', $html);
        $this->assertStringContainsString(__('Pickup location'), $html);
        $this->assertStringContainsString(config('box.pickup_location.name'), $html);
        $this->assertStringNotContainsString(__('Your delivery address'), $html);
    }

    public function test_verify_email_skips_summary_when_no_subscription(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->get(route('verification.notice'));
        $response->assertOk();

        $this->assertStringNotContainsString('verify-email-confirmation', $response->getContent());
    }
}
