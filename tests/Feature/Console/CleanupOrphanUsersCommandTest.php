<?php

namespace Tests\Feature\Console;

use App\Enums\OrderProductType;
use App\Models\Address;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * W2-13 — `users:cleanup-orphans` deletes unverified User rows that never
 * picked a plan AND never placed a trial order, older than N days.
 */
class CleanupOrphanUsersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_unverified_user_with_no_subscription_and_no_orders_older_than_cutoff(): void
    {
        $orphan = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(20),
        ]);

        $this->artisan('users:cleanup-orphans', ['--older-than-days' => 14])
            ->expectsOutputToContain('Deleted 1 orphan user row')
            ->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $orphan->id]);
    }

    public function test_keeps_user_with_subscription(): void
    {
        $kept = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(30),
        ]);
        Subscription::factory()->for($kept)->create();

        $this->artisan('users:cleanup-orphans')
            ->expectsOutputToContain('No orphan users found')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $kept->id]);
    }

    public function test_keeps_user_with_trial_order(): void
    {
        $kept = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(30),
        ]);
        $address = Address::factory()->for($kept)->create();
        Order::create([
            'user_id' => $kept->id,
            'address_id' => $address->id,
            'product_type' => OrderProductType::TrialBox,
            'box_size' => 'small',
            'amount' => 35.00,
            'currency' => 'EUR',
            'status' => 'pending_payment',
            'preferences' => [],
            'allergies' => [],
        ]);

        $this->artisan('users:cleanup-orphans')
            ->expectsOutputToContain('No orphan users found')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $kept->id]);
    }

    public function test_keeps_user_who_verified_email(): void
    {
        $kept = User::factory()->create([
            'email_verified_at' => now()->subDays(30),
            'created_at' => now()->subDays(30),
        ]);

        $this->artisan('users:cleanup-orphans')
            ->expectsOutputToContain('No orphan users found')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $kept->id]);
    }

    public function test_keeps_admin_users_even_when_otherwise_orphaned(): void
    {
        $admin = User::factory()->create([
            'email_verified_at' => null,
            'is_admin' => true,
            'created_at' => now()->subDays(60),
        ]);

        $this->artisan('users:cleanup-orphans')
            ->expectsOutputToContain('No orphan users found')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_keeps_recent_unverified_users_below_cutoff(): void
    {
        // Created 5 days ago — well under default 14-day cutoff.
        $recent = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(5),
        ]);

        $this->artisan('users:cleanup-orphans', ['--older-than-days' => 14])
            ->expectsOutputToContain('No orphan users found')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $recent->id]);
    }

    public function test_dry_run_lists_candidates_without_deleting(): void
    {
        $orphan = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(20),
            'email' => 'abandoned@example.com',
        ]);

        $this->artisan('users:cleanup-orphans', ['--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->expectsOutputToContain('abandoned@example.com')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $orphan->id]);
    }
}
