<?php

namespace Tests\Feature\Restaurant;

use App\Enums\AccountType;
use App\Enums\RestaurantVerificationStatus;
use App\Mail\RestaurantApplicationAdminNotification;
use App\Mail\RestaurantApprovedMail;
use App\Mail\RestaurantRejectedMail;
use App\Models\RestaurantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RestaurantAccountTest extends TestCase
{
    use RefreshDatabase;

    private function validApplication(array $overrides = []): array
    {
        return array_merge([
            'business_name' => 'Restaurant Olive',
            'contact_name' => 'Mara Koch',
            'email' => 'olive@example.com',
            'phone' => '+49 30 1234567',
            'password' => 'sehr-geheim-123',
            'password_confirmation' => 'sehr-geheim-123',
            'handelsregisternummer' => 'HRB 12345',
            'ust_id_nr' => 'DE123456789',
            'billing_street' => 'Marktstr. 1',
            'billing_postal_code' => '10115',
            'billing_city' => 'Berlin',
        ], $overrides);
    }

    public function test_signup_page_renders(): void
    {
        $this->get(route('restaurant.register'))
            ->assertOk()
            ->assertSee('restaurant-register-form', false);
    }

    public function test_signup_creates_pending_account_notifies_admins_and_does_not_log_in(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->post(route('restaurant.register.store'), $this->validApplication());

        $response->assertRedirect(route('restaurant.register.submitted'));
        $this->assertGuest();

        $user = User::where('email', 'olive@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(AccountType::Restaurant, $user->account_type);
        $this->assertNull($user->email_verified_at);

        $profile = $user->restaurantProfile;
        $this->assertNotNull($profile);
        $this->assertTrue($profile->isPending());
        $this->assertSame('HRB 12345', $profile->handelsregisternummer);

        Mail::assertSent(RestaurantApplicationAdminNotification::class, fn ($mail) => $mail->hasTo($admin->email));
    }

    public function test_signup_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'olive@example.com']);

        $this->post(route('restaurant.register.store'), $this->validApplication())
            ->assertSessionHasErrors('email');
    }

    public function test_signed_verify_link_activates_account_and_emails_welcome(): void
    {
        Mail::fake();
        $admin = User::factory()->admin()->create();
        $profile = RestaurantProfile::factory()->create();

        $url = URL::signedRoute('restaurant.verify.approve', [
            'restaurantProfile' => $profile->id,
            'admin' => $admin->id,
        ]);

        $this->post($url)->assertRedirect(route('restaurant.verify.done', ['result' => 'verified']));

        $profile->refresh();
        $this->assertSame(RestaurantVerificationStatus::Verified, $profile->verification_status);
        $this->assertNotNull($profile->verified_at);
        $this->assertSame($admin->id, $profile->verified_by_user_id);
        $this->assertNotNull($profile->user->email_verified_at);

        Mail::assertSent(RestaurantApprovedMail::class, fn ($mail) => $mail->hasTo($profile->user->email));
    }

    public function test_verify_is_idempotent(): void
    {
        Mail::fake();
        $profile = RestaurantProfile::factory()->verified()->create();

        $url = URL::signedRoute('restaurant.verify.approve', ['restaurantProfile' => $profile->id]);

        $this->post($url)->assertRedirect(route('restaurant.verify.done', ['result' => 'already']));
        Mail::assertNotSent(RestaurantApprovedMail::class);
    }

    public function test_signed_reject_deletes_account_and_emails(): void
    {
        Mail::fake();
        $profile = RestaurantProfile::factory()->create();
        $userId = $profile->user_id;
        $email = $profile->user->email;

        $url = URL::signedRoute('restaurant.verify.reject', ['restaurantProfile' => $profile->id]);

        $this->post($url, ['reason' => 'Kein Liefergebiet'])
            ->assertRedirect(route('restaurant.verify.done', ['result' => 'rejected']));

        $this->assertDatabaseMissing('users', ['id' => $userId]);
        $this->assertDatabaseMissing('restaurant_profiles', ['id' => $profile->id]);

        Mail::assertSent(RestaurantRejectedMail::class, fn ($mail) => $mail->hasTo($email));
    }

    public function test_review_link_rejects_tampered_signature(): void
    {
        $profile = RestaurantProfile::factory()->create();

        $this->get(route('restaurant.verify.review', ['restaurantProfile' => $profile->id]).'?signature=deadbeef')
            ->assertForbidden();
    }

    public function test_guard_redirects_pending_restaurant_to_pending_page(): void
    {
        $profile = RestaurantProfile::factory()->create();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.dashboard'))
            ->assertRedirect(route('app.restaurant.pending'));
    }

    public function test_guard_admits_verified_restaurant(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();

        $this->actingAs($profile->user)
            ->get(route('app.restaurant.dashboard'))
            ->assertOk();
    }

    public function test_guard_forbids_non_restaurant_customer(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)
            ->get(route('app.restaurant.dashboard'))
            ->assertForbidden();
    }

    public function test_guard_redirects_guests_to_login(): void
    {
        $this->get(route('app.restaurant.dashboard'))->assertRedirect(route('auth.login'));
    }

    public function test_verified_restaurant_login_lands_on_restaurant_dashboard(): void
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $profile->user->forceFill(['password' => bcrypt('password')])->save();

        $this->post(route('auth.login.store'), [
            'email' => $profile->user->email,
            'password' => 'password',
        ])->assertRedirect(route('app.restaurant.dashboard'));
    }

    public function test_admin_queue_lists_pending_applications(): void
    {
        $admin = User::factory()->admin()->create();
        $profile = RestaurantProfile::factory()->create(['business_name' => 'Bistro Beet']);

        $this->actingAs($admin)
            ->get(route('app.admin.restaurant-accounts.index'))
            ->assertOk()
            ->assertSee('Bistro Beet');
    }
}
