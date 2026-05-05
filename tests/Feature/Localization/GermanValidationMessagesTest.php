<?php

namespace Tests\Feature\Localization;

use App\Models\OnboardingDraft;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the K3 / B4 finding (notes file) — German UI must show German
 * validation errors, not the default Laravel English fallback. The fix
 * lives in lang/de/validation.php (rule messages + attribute labels).
 *
 * The integration assertion uses the onboarding address step because it
 * has the most-cited offender from the review ("street line 1" raw
 * snake_case in the English error).
 */
class GermanValidationMessagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $extraData
     */
    private function startDraftWithIntent(User $user, string $intent, array $extraData = []): OnboardingDraft
    {
        return OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => $intent,
            'step' => OnboardingDraftManager::STEP_ADDRESS,
            'data' => array_merge([
                'account_completed' => true,
            ], $extraData),
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_address_step_shows_german_required_messages_with_friendly_labels(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            // SetLocale middleware reads the user's persisted locale; this
            // mirrors a real German-speaking signed-in customer.
            'locale' => 'de',
        ]);
        $this->startDraftWithIntent($user, OnboardingDraftManager::INTENT_SUBSCRIPTION);

        $response = $this->actingAs($user)
            ->from(route('app.onboarding.step.address'))
            ->post(route('app.onboarding.step.address.store'), [
                // Empty submission: every required field must error in German
                // with its friendly label (`recipient_name` → "Empfängername"
                // etc.), not raw snake_case.
            ]);

        $response->assertRedirect(route('app.onboarding.step.address'));
        $response->assertSessionHasErrors([
            'recipient_name', 'street_line_1', 'postal_code', 'city',
        ]);

        $errors = session('errors')->getBag('default');

        // Friendly labels.
        $this->assertStringContainsString('Empfängername', $errors->first('recipient_name'));
        $this->assertStringContainsString('Straße und Hausnummer', $errors->first('street_line_1'));
        $this->assertStringContainsString('PLZ', $errors->first('postal_code'));
        $this->assertStringContainsString('Stadt', $errors->first('city'));

        // German rule message ("ist erforderlich"), not "field is required".
        $this->assertStringContainsString('ist erforderlich', $errors->first('recipient_name'));

        // No English leakage anywhere.
        foreach (['recipient_name', 'street_line_1', 'postal_code', 'city'] as $field) {
            $this->assertStringNotContainsString('field is required', $errors->first($field));
            $this->assertStringNotContainsString('street line 1', $errors->first($field));
            $this->assertStringNotContainsString('postal code', $errors->first($field));
        }
    }

    public function test_account_step_shows_german_email_taken_message(): void
    {
        // Pre-existing user with the email so the second submit triggers
        // a unique-rule violation.
        User::factory()->create(['email' => 'taken@example.test']);

        $response = $this->withHeaders(['Accept-Language' => 'de'])
            ->from(route('app.onboarding.step.account'))
            ->post(route('app.onboarding.step.account.store'), [
                'name' => 'Test',
                'email' => 'taken@example.test',
                'password' => 'somelongpassword',
                'password_confirmation' => 'somelongpassword',
            ]);

        $response->assertSessionHasErrors(['email']);

        $emailError = session('errors')->getBag('default')->first('email');

        // German "ist bereits vergeben" instead of English "has already been taken".
        $this->assertStringContainsString('bereits vergeben', $emailError);
        $this->assertStringNotContainsString('has already been taken', $emailError);
    }
}
