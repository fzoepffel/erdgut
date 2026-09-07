<?php

namespace Tests\Feature\Admin;

use App\Mail\MercurialMail;
use App\Models\Mercurial;
use App\Models\MercurialRecipient;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Support\MercurialHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminMercurialTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.admin.mercurial.index'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('app.admin.mercurial.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_verified_restaurants_but_not_unverified(): void
    {
        $verified = RestaurantProfile::factory()->verified()->create([
            'business_name' => 'Restaurant Otto',
        ]);
        $pending = RestaurantProfile::factory()->create([
            'business_name' => 'Restaurant Pending',
        ]);

        $this->actingAs($this->admin())
            ->get(route('app.admin.mercurial.index'))
            ->assertOk()
            ->assertSee($verified->user->email)
            ->assertDontSee($pending->user->email);
    }

    public function test_composer_prefills_both_language_subjects_with_current_week(): void
    {
        $admin = User::factory()->admin()->create(['locale' => 'de']);
        $week = str_pad((string) now()->isoWeek, 2, '0', STR_PAD_LEFT);

        $this->actingAs($admin)
            ->get(route('app.admin.mercurial.index'))
            ->assertOk()
            ->assertSee("Angebot KW{$week}", false)      // German default subject
            ->assertSee("offer week {$week}", false);    // English default subject
    }

    public function test_default_subject_leaves_no_unfilled_week_placeholder(): void
    {
        $this->actingAs($this->admin())
            ->get(route('app.admin.mercurial.index'))
            ->assertOk()
            ->assertDontSee(':week', false);
    }

    public function test_composer_lists_contact_restaurant_and_person(): void
    {
        MercurialRecipient::query()->create([
            'email' => 'louis@otto.de',
            'name' => 'Louis',
            'restaurant' => 'Otto',
            'locale' => 'en',
        ]);

        $this->actingAs($this->admin())
            ->get(route('app.admin.mercurial.index'))
            ->assertOk()
            ->assertSee('Otto')
            ->assertSee('Louis')
            ->assertSee('louis@otto.de');
    }

    public function test_test_send_mails_the_admin_with_substituted_tokens(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('app.admin.mercurial.test'), [
                'subject_de' => 'Angebot {{restaurant}}',
                'body_de' => '<p>Liebe {{name}}, hier das Angebot.</p>',
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertSent(MercurialMail::class, function (MercurialMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && str_contains($mail->subjectLine, __('mercurial.sample_restaurant'))
                && str_contains($mail->bodyHtml, $admin->name)
                && ! str_contains($mail->bodyHtml, '{{name}}');
        });
    }

    public function test_test_send_sends_one_preview_per_filled_language(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.test'), [
                'subject_de' => 'Angebot',
                'body_de' => '<p>Hallo</p>',
                'subject_en' => 'Offer',
                'body_en' => '<p>Hi</p>',
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertSent(MercurialMail::class, 2);
    }

    public function test_send_queues_one_mail_per_recipient_and_logs_history(): void
    {
        Mail::fake();

        $r1 = RestaurantProfile::factory()->verified()->create(['contact_name' => 'Maya']);
        $r2 = RestaurantProfile::factory()->verified()->create(['contact_name' => 'Ben']);

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject_de' => 'Angebot KW35',
                'body_de' => '<p>Liebe {{name}},</p>',
                'recipients' => [$r1->user->email, $r2->user->email],
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertQueued(MercurialMail::class, 2);
        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo($r1->user->email)
            && str_contains($m->bodyHtml, 'Maya'));

        $this->assertDatabaseCount('mercurials', 1);
        $this->assertSame(2, Mercurial::query()->first()->recipient_count);
    }

    public function test_send_routes_each_recipient_to_their_language(): void
    {
        Mail::fake();

        $german = MercurialRecipient::query()->create([
            'email' => 'de@bistro.de', 'name' => 'Maya', 'restaurant' => 'Bistro', 'locale' => 'de',
        ]);
        $english = MercurialRecipient::query()->create([
            'email' => 'en@otto.de', 'name' => 'Louis', 'restaurant' => 'Otto', 'locale' => 'en',
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject_de' => 'Erdgut & {{restaurant}} – Angebot',
                'body_de' => '<p>Liebe {{name}},</p>',
                'subject_en' => 'Erdgut & {{restaurant}} – offer',
                'body_en' => '<p>Dear {{name}},</p>',
                'recipients' => [$german->email, $english->email],
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo('de@bistro.de')
            && $m->subjectLine === 'Erdgut & Bistro – Angebot'
            && str_contains($m->bodyHtml, 'Liebe Maya,'));

        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo('en@otto.de')
            && $m->subjectLine === 'Erdgut & Otto – offer'
            && str_contains($m->bodyHtml, 'Dear Louis,'));

        $log = Mercurial::query()->first();
        $this->assertStringContainsString('Liebe', $log->body_html);
        $this->assertStringContainsString('Dear', $log->body_html_en);
    }

    public function test_send_is_blocked_when_a_needed_language_version_is_missing(): void
    {
        Mail::fake();

        $english = MercurialRecipient::query()->create([
            'email' => 'en@otto.de', 'name' => 'Louis', 'restaurant' => 'Otto', 'locale' => 'en',
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject_de' => 'Angebot',
                'body_de' => '<p>Hallo</p>',
                // No English version, but an English recipient is selected.
                'recipients' => [$english->email],
            ])
            ->assertSessionHasErrors('body_en');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('mercurials', 0);
    }

    public function test_send_persists_hand_typed_adhoc_email_with_restaurant_and_language(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject_en' => 'Erdgut & {{restaurant}} – offer',
                'body_en' => '<p>Dear {{name}},</p>',
                'recipients' => ['louis@otto.de'],
                'adhoc_names' => ['louis@otto.de' => 'Louis'],
                'adhoc_restaurants' => ['louis@otto.de' => 'Otto'],
                'adhoc_locales' => ['louis@otto.de' => 'en'],
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        $this->assertDatabaseHas('mercurial_recipients', [
            'email' => 'louis@otto.de',
            'name' => 'Louis',
            'restaurant' => 'Otto',
            'locale' => 'en',
        ]);

        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo('louis@otto.de')
            && $m->subjectLine === 'Erdgut & Otto – offer'
            && str_contains($m->bodyHtml, 'Dear Louis,'));
    }

    public function test_send_requires_at_least_one_recipient(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject_de' => 'Angebot',
                'body_de' => '<p>Hallo</p>',
                'recipients' => [],
            ])
            ->assertSessionHasErrors('recipients');

        Mail::assertNothingQueued();
    }

    public function test_set_recipient_locale_persists_for_contact(): void
    {
        $contact = MercurialRecipient::query()->create([
            'email' => 'chef@bistro.de', 'name' => 'Maya', 'restaurant' => 'Bistro', 'locale' => 'de',
        ]);

        $this->actingAs($this->admin())
            ->postJson(route('app.admin.mercurial.recipient-locale'), [
                'email' => 'chef@bistro.de',
                'locale' => 'en',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'locale' => 'en']);

        $this->assertSame('en', $contact->fresh()->locale);
    }

    public function test_set_recipient_locale_persists_for_restaurant_without_touching_account_locale(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->update(['locale' => 'de']);

        $this->actingAs($this->admin())
            ->postJson(route('app.admin.mercurial.recipient-locale'), [
                'email' => $profile->user->email,
                'locale' => 'en',
            ])
            ->assertOk();

        $this->assertSame('en', $profile->fresh()->mercurial_locale);
        // Account/shop language is untouched.
        $this->assertSame('de', $profile->user->fresh()->locale);
    }

    public function test_store_and_destroy_contact(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('app.admin.mercurial.contacts.store'), [
                'email' => 'Chef@Example.com',
                'name' => 'Chef',
                'restaurant' => 'Example Restaurant',
                'locale' => 'en',
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        $this->assertDatabaseHas('mercurial_recipients', [
            'email' => 'chef@example.com',
            'restaurant' => 'Example Restaurant',
            'locale' => 'en',
        ]);

        $contact = MercurialRecipient::query()->where('email', 'chef@example.com')->first();

        $this->actingAs($admin)
            ->delete(route('app.admin.mercurial.contacts.destroy', $contact))
            ->assertRedirect(route('app.admin.mercurial.index'));

        $this->assertDatabaseMissing('mercurial_recipients', ['email' => 'chef@example.com']);
    }

    public function test_sanitizer_strips_scripts_and_keeps_safe_links(): void
    {
        $dirty = '<p>Hallo <a href="https://erd-gut.de/kw35" onclick="x()">Mercurial</a></p>'
            .'<script>alert(1)</script>';

        $clean = MercurialHtml::sanitize($dirty);

        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('href="https://erd-gut.de/kw35"', $clean);
        $this->assertStringContainsString('Mercurial', $clean);
    }
}
