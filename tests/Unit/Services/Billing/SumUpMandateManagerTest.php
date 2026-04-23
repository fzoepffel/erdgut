<?php

namespace Tests\Unit\Services\Billing;

use App\Models\PaymentMandate;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing\SumUpMandateManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SumUpMandateManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_mandate_creates_active_mandate_when_none_exists(): void
    {
        $user = User::factory()->create();
        $manager = app(SumUpMandateManager::class);

        $mandate = $manager->ensureMandate($user);

        $this->assertSame($user->id, $mandate->user_id);
        $this->assertSame('sumup', $mandate->provider);
        $this->assertTrue($mandate->isActive());
        $this->assertNull($mandate->provider_token);
        $this->assertNull($mandate->provider_customer_id);
    }

    public function test_ensure_mandate_returns_existing_active_mandate(): void
    {
        $user = User::factory()->create();
        $manager = app(SumUpMandateManager::class);

        $first = $manager->ensureMandate($user);
        $second = $manager->ensureMandate($user);

        $this->assertSame($first->id, $second->id);
    }

    public function test_ensure_mandate_skips_revoked_mandates(): void
    {
        $user = User::factory()->create();
        $manager = app(SumUpMandateManager::class);

        $revoked = $manager->ensureMandate($user);
        $manager->revoke($revoked);

        $fresh = $manager->ensureMandate($user);

        $this->assertNotSame($revoked->id, $fresh->id);
        $this->assertTrue($fresh->isActive());
    }

    public function test_store_token_and_attach_customer_persist_encrypted_values(): void
    {
        $user = User::factory()->create();
        $manager = app(SumUpMandateManager::class);

        $mandate = $manager->ensureMandate($user);
        $manager->attachCustomer($mandate, 'cust_abc');
        $manager->storeToken($mandate, 'tok_xyz');

        $reloaded = $mandate->fresh();
        $this->assertSame('cust_abc', $reloaded->provider_customer_id);
        $this->assertSame('tok_xyz', $reloaded->provider_token);
    }

    public function test_link_to_subscription_sets_fk(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create();
        $manager = app(SumUpMandateManager::class);

        $mandate = $manager->ensureMandate($user);
        $manager->linkToSubscription($subscription, $mandate);

        $this->assertSame($mandate->id, $subscription->fresh()->payment_mandate_id);
    }

    public function test_revoke_is_idempotent_and_records_audit_event(): void
    {
        $user = User::factory()->create();
        $manager = app(SumUpMandateManager::class);

        $mandate = $manager->ensureMandate($user);
        $manager->revoke($mandate, 'user removed card');

        $this->assertSame(PaymentMandate::STATUS_REVOKED, $mandate->fresh()->status);
        $this->assertNotNull($mandate->fresh()->revoked_at);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => PaymentMandate::class,
            'entity_id' => $mandate->id,
            'action' => 'revoked',
            'from_state' => PaymentMandate::STATUS_ACTIVE,
            'to_state' => PaymentMandate::STATUS_REVOKED,
            'reason' => 'user removed card',
        ]);

        $manager->revoke($mandate, 'again');
        $this->assertSame(1, $user->paymentMandates()->where('status', PaymentMandate::STATUS_REVOKED)->count());
    }

    public function test_subscription_active_sumup_token_prefers_mandate(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'sumup_token' => 'legacy_token',
            'sumup_customer_id' => 'legacy_cust',
        ]);
        $manager = app(SumUpMandateManager::class);

        $mandate = $manager->ensureMandate($user);
        $manager->attachCustomer($mandate, 'mandate_cust');
        $manager->storeToken($mandate, 'mandate_token');
        $manager->linkToSubscription($subscription, $mandate);

        $subscription = $subscription->fresh();
        $this->assertSame('mandate_token', $subscription->activeSumupToken());
        $this->assertSame('mandate_cust', $subscription->activeSumupCustomerId());
    }

    public function test_subscription_falls_back_to_legacy_column_when_no_mandate(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'sumup_token' => 'legacy_token',
            'sumup_customer_id' => 'legacy_cust',
            'payment_mandate_id' => null,
        ]);

        $this->assertSame('legacy_token', $subscription->activeSumupToken());
        $this->assertSame('legacy_cust', $subscription->activeSumupCustomerId());
    }

    public function test_subscription_falls_back_to_legacy_when_mandate_revoked(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->for($user)->create([
            'sumup_token' => 'legacy_token',
            'sumup_customer_id' => 'legacy_cust',
        ]);
        $manager = app(SumUpMandateManager::class);

        $mandate = $manager->ensureMandate($user);
        $manager->attachCustomer($mandate, 'mandate_cust');
        $manager->storeToken($mandate, 'mandate_token');
        $manager->linkToSubscription($subscription, $mandate);
        $manager->revoke($mandate);

        $subscription = $subscription->fresh();
        $this->assertSame('legacy_token', $subscription->activeSumupToken());
    }
}
