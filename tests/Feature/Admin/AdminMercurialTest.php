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

    public function test_composer_prefills_subject_with_current_calendar_week(): void
    {
        // Pin the request locale to German so the KW label is deterministic.
        $admin = User::factory()->admin()->create(['locale' => 'de']);
        $week = str_pad((string) now()->isoWeek, 2, '0', STR_PAD_LEFT);

        $this->actingAs($admin)
            ->get(route('app.admin.mercurial.index'))
            ->assertOk()
            ->assertSee("Angebot KW{$week}", false);
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
                'subject' => 'Angebot {{restaurant}}',
                'body_html' => '<p>Liebe {{name}}, hier das Angebot.</p>',
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertSent(MercurialMail::class, function (MercurialMail $mail) use ($admin) {
            return $mail->hasTo($admin->email)
                && str_contains($mail->subjectLine, __('mercurial.sample_restaurant'))
                && str_contains($mail->bodyHtml, $admin->name)
                && ! str_contains($mail->bodyHtml, '{{name}}');
        });
    }

    public function test_send_queues_one_mail_per_recipient_and_logs_history(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $r1 = RestaurantProfile::factory()->verified()->create(['contact_name' => 'Maya']);
        $r2 = RestaurantProfile::factory()->verified()->create(['contact_name' => 'Ben']);

        $this->actingAs($admin)
            ->post(route('app.admin.mercurial.send'), [
                'subject' => 'Angebot KW35',
                'body_html' => '<p>Liebe {{name}},</p>',
                'recipients' => [$r1->user->email, $r2->user->email],
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertQueued(MercurialMail::class, 2);
        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo($r1->user->email)
            && str_contains($m->bodyHtml, 'Maya'));

        $this->assertDatabaseCount('mercurials', 1);
        $this->assertSame(2, Mercurial::query()->first()->recipient_count);
    }

    public function test_send_persists_hand_typed_adhoc_email_with_restaurant(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject' => 'Erdgut & {{restaurant}} – Angebot',
                'body_html' => '<p>Liebe {{name}},</p>',
                'recipients' => ['louis@otto.de'],
                'adhoc_names' => ['louis@otto.de' => 'Louis'],
                'adhoc_restaurants' => ['louis@otto.de' => 'Otto'],
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        // Persisted with both the contact person and the restaurant.
        $this->assertDatabaseHas('mercurial_recipients', [
            'email' => 'louis@otto.de',
            'name' => 'Louis',
            'restaurant' => 'Otto',
        ]);

        // The restaurant fills {{restaurant}}, the person fills {{name}}.
        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo('louis@otto.de')
            && $m->subjectLine === 'Erdgut & Otto – Angebot'
            && str_contains($m->bodyHtml, 'Liebe Louis,'));
    }

    public function test_saved_contact_restaurant_fills_placeholder_on_send(): void
    {
        Mail::fake();

        $contact = MercurialRecipient::query()->create([
            'email' => 'chef@bistro.de',
            'name' => 'Maya',
            'restaurant' => 'Bistro',
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject' => 'Erdgut & {{restaurant}}',
                'body_html' => '<p>Liebe {{name}},</p>',
                'recipients' => [$contact->email],
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        Mail::assertQueued(MercurialMail::class, fn (MercurialMail $m) => $m->hasTo('chef@bistro.de')
            && $m->subjectLine === 'Erdgut & Bistro'
            && str_contains($m->bodyHtml, 'Liebe Maya,'));
    }

    public function test_send_requires_at_least_one_recipient(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('app.admin.mercurial.send'), [
                'subject' => 'Angebot',
                'body_html' => '<p>Hallo</p>',
                'recipients' => [],
            ])
            ->assertSessionHasErrors('recipients');

        Mail::assertNothingQueued();
    }

    public function test_store_and_destroy_contact(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('app.admin.mercurial.contacts.store'), [
                'email' => 'Chef@Example.com',
                'name' => 'Chef',
                'restaurant' => 'Example Restaurant',
            ])
            ->assertRedirect(route('app.admin.mercurial.index'));

        // Stored lowercased, with the restaurant name.
        $this->assertDatabaseHas('mercurial_recipients', [
            'email' => 'chef@example.com',
            'restaurant' => 'Example Restaurant',
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
