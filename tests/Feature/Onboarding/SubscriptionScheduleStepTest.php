<?php

namespace Tests\Feature\Onboarding;

use App\Enums\Timeslot;
use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionScheduleStepTest extends TestCase
{
    use RefreshDatabase;

    private function draftFor(User $user): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_SUBSCRIPTION_SCHEDULE,
            'data' => [
                'account_completed' => true,
                'address_completed' => true,
                'preferences_completed' => true,
                'commitment_completed' => true,
            ],
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_schedule_step_renders_weekday_and_timeslot_options(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user);

        $this->actingAs($user)
            ->get(route('app.onboarding.step.subscription_schedule'))
            ->assertOk()
            ->assertSee('name="delivery_weekday"', false)
            ->assertSee('name="preferred_timeslot"', false)
            ->assertSee(Timeslot::Morning->value);
    }

    public function test_posting_valid_weekday_and_timeslot_persists_to_draft(): void
    {
        $user = User::factory()->create();
        $draft = $this->draftFor($user);

        $this->actingAs($user)
            ->post(route('app.onboarding.step.subscription_schedule.store'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'delivery_weekday' => 3,
                'preferred_timeslot' => Timeslot::Afternoon->value,
                'delivery_instructions' => null,
            ])
            ->assertRedirect(route('app.onboarding.step.subscription_payment'));

        $draft->refresh();
        $this->assertSame(3, $draft->data['delivery_weekday']);
        $this->assertSame(Timeslot::Afternoon->value, $draft->data['preferred_timeslot']);
    }

    public function test_weekday_outside_zone_window_is_rejected(): void
    {
        $user = User::factory()->create();
        $draft = $this->draftFor($user);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.subscription_schedule'))
            ->post(route('app.onboarding.step.subscription_schedule.store'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                // zone allows Tue-Fri (2-5); Sunday (0) is outside window
                'delivery_weekday' => 0,
                'preferred_timeslot' => Timeslot::Morning->value,
            ])
            ->assertSessionHasErrors('delivery_weekday');

        $draft->refresh();
        $this->assertArrayNotHasKey('delivery_weekday', $draft->data);
    }

    public function test_weekday_is_required_when_delivery_fulfillment_chosen(): void
    {
        $user = User::factory()->create();
        $this->draftFor($user);

        $this->actingAs($user)
            ->from(route('app.onboarding.step.subscription_schedule'))
            ->post(route('app.onboarding.step.subscription_schedule.store'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'preferred_timeslot' => Timeslot::Morning->value,
            ])
            ->assertSessionHasErrors('delivery_weekday');
    }
}
