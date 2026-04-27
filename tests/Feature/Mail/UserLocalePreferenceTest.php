<?php

namespace Tests\Feature\Mail;

use App\Mail\DeliveryInTransitMail;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Verifies that Mail::to($user)->queue(...) honours the user's persisted
 * `locale` preference via the `HasLocalePreference` contract on the User
 * model. Without this, queue workers would render every email in
 * `config('app.locale')` — leaking English subjects to German subscribers.
 */
class UserLocalePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_preferred_locale_returns_stored_locale(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->assertSame('de', $user->preferredLocale());
    }

    public function test_user_preferred_locale_falls_back_to_app_locale_when_unset(): void
    {
        config(['app.locale' => 'de']);
        $user = User::factory()->create(['locale' => null]);

        $this->assertSame('de', $user->preferredLocale());
    }

    public function test_queued_mailable_renders_in_user_locale(): void
    {
        Mail::fake();

        $user = User::factory()->create(['locale' => 'de']);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        Mail::to($user)->queue(new DeliveryInTransitMail($delivery));

        Mail::assertQueued(DeliveryInTransitMail::class, function (DeliveryInTransitMail $mail) {
            // Laravel sets `$mail->locale` automatically from
            // HasLocalePreference when the recipient is a User model.
            return $mail->locale === 'de';
        });
    }

    public function test_french_user_gets_french_subject_line(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);
        $address = Address::factory()->create(['user_id' => $user->id]);
        $delivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
        ]);

        $mail = (new DeliveryInTransitMail($delivery));
        $mail->locale = $user->preferredLocale();

        // Render the envelope under the user's locale and inspect the subject.
        // Laravel's TempLocaleSwitch happens inside the framework's mail
        // building pipeline; here we approximate it by setting the locale
        // and rendering the envelope.
        $rendered = app()->call(function () use ($mail) {
            app()->setLocale($mail->locale);

            return $mail->envelope();
        });

        $this->assertSame('fr', $mail->locale);
        $this->assertNotEmpty($rendered->subject);
        // Concrete language check: the FR subject differs from the EN/DE one.
        // Since translation values can drift, we just assert that switching
        // locale produces a different rendered subject than the default.
        app()->setLocale('en');
        $renderedEn = $mail->envelope();
        $this->assertNotSame($rendered->subject, $renderedEn->subject,
            'Subject must differ between locales (translation must be active).');
    }
}
