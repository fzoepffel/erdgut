<?php

namespace Tests\Feature\Onboarding;

use App\Models\Address;
use App\Models\OnboardingDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * W2-17: the wizard right-rail must show the customer's running shopping
 * cart of every piece of information they've handed over so far —
 * progressively, with edit links — driven by `OnboardingDraft.data`.
 */
class OnboardingSummaryComponentTest extends TestCase
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

    public function test_renders_empty_summary_when_nothing_filled_yet(): void
    {
        $user = User::factory()->create();
        $draft = OnboardingDraft::create([
            'user_id' => $user->id,
            'data' => [],
            'expires_at' => now()->addHour(),
        ]);

        $html = $this->actingAs($user)
            ->blade('<x-onboarding.summary :draft="$draft" :user="$user" />', [
                'draft' => $draft,
                'user' => $user,
            ]);

        $html->assertSee(__('Your details'));
        $html->assertSee(__('We will keep a running summary of everything you tell us as you go.'));
        $html->assertDontSee('onboarding-summary-account', false);
        $html->assertDontSee('onboarding-summary-box', false);
    }

    public function test_progressively_reveals_account_address_box_schedule_blocks(): void
    {
        $user = User::factory()->create([
            'name' => 'Anna Schmitz',
            'email' => 'anna@example.test',
        ]);
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
            'street_line_1' => 'Kastanienallee 12',
            'postal_code' => '10435',
            'city' => 'Berlin',
        ]);

        $draft = OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => 'subscription',
            'data' => [
                'account_completed' => true,
                'address_id' => $address->id,
                'address_completed' => true,
                'box_size' => 'small',
                'preferences' => ['eggs_dairy_add_on' => true],
                'allergies' => ['gluten'],
                'preferences_completed' => true,
                'plan' => 'weekly',
                'preferred_timeslot' => '12:00-16:00',
                'fulfillment_mode' => 'delivery',
            ],
            'expires_at' => now()->addHour(),
        ]);

        $html = $this->actingAs($user)
            ->blade('<x-onboarding.summary :draft="$draft" :user="$user" />', [
                'draft' => $draft,
                'user' => $user,
            ]);

        // All four blocks render.
        $html->assertSee('onboarding-summary-account', false);
        $html->assertSee('onboarding-summary-address', false);
        $html->assertSee('onboarding-summary-box', false);
        $html->assertSee('onboarding-summary-schedule', false);

        // Specific data flowed through.
        $html->assertSee('Anna Schmitz');
        $html->assertSee('anna@example.test');
        $html->assertSee('Kastanienallee 12');
        $html->assertSee('10435');
        $html->assertSee('Berlin');
        // Box price = 20 + 4.50 = 24.50, formatted German.
        $html->assertSee('24,50');

        // Edit links route back to the right step.
        $html->assertSee(route('app.onboarding.step.account'), false);
        $html->assertSee(route('app.onboarding.step.address'), false);
        $html->assertSee(route('app.onboarding.step.preferences'), false);
        $html->assertSee(route('app.onboarding.step.subscription_schedule'), false);
    }

    public function test_box_block_does_not_render_without_box_size(): void
    {
        $user = User::factory()->create();
        $draft = OnboardingDraft::create([
            'user_id' => $user->id,
            'data' => [
                'account_completed' => true,
            ],
            'expires_at' => now()->addHour(),
        ]);

        $html = $this->actingAs($user)
            ->blade('<x-onboarding.summary :draft="$draft" :user="$user" />', [
                'draft' => $draft,
                'user' => $user,
            ]);

        $html->assertSee('onboarding-summary-account', false);
        $html->assertDontSee('onboarding-summary-box', false);
    }
}
