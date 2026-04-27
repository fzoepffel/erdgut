<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditEventIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get(route('app.admin.audit-events.index'))
            ->assertRedirect(route('auth.login'));
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.audit-events.index'))
            ->assertForbidden();
    }

    public function test_admin_can_see_audit_events_in_reverse_chronological_order(): void
    {
        $admin = User::factory()->admin()->create();

        // Use distinct reason strings that only appear in the events table
        // (not in the filter dropdown), so position-based assertions are reliable.
        AuditLogger::log('subscription', 1, 'pause', null, 'paused', 'reason-OLDEST', [], $admin->id);
        AuditLogger::log('subscription', 1, 'cancel', 'paused', 'cancelled', 'reason-MIDDLE', [], $admin->id);
        AuditLogger::log('payment', 5, 'refund', 'paid', 'refunded', 'reason-NEWEST', [], $admin->id);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.audit-events.index'))
            ->assertOk();

        $body = $response->getContent();
        // Newest event renders first.
        $newest = strpos($body, 'reason-NEWEST');
        $oldest = strpos($body, 'reason-OLDEST');
        $this->assertNotFalse($newest);
        $this->assertNotFalse($oldest);
        $this->assertLessThan($oldest, $newest, 'Newer events must render before older ones (reverse chronological).');
    }

    public function test_admin_can_filter_by_entity_type(): void
    {
        $admin = User::factory()->admin()->create();
        // Distinct reason strings so we avoid collisions with the filter
        // dropdown which also lists action names.
        AuditLogger::log('subscription', 1, 'pause', reason: 'sub-reason-marker', actorUserId: $admin->id);
        AuditLogger::log('payment', 5, 'refund', reason: 'pay-reason-marker', actorUserId: $admin->id);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.audit-events.index', ['entity_type' => 'payment']))
            ->assertOk();

        $response->assertSee('pay-reason-marker');
        $response->assertDontSee('sub-reason-marker');
    }

    public function test_admin_can_filter_by_entity_id(): void
    {
        $admin = User::factory()->admin()->create();
        AuditLogger::log('subscription', 1, 'pause', reason: 'sub1-marker', actorUserId: $admin->id);
        AuditLogger::log('subscription', 2, 'cancel', reason: 'sub2-marker', actorUserId: $admin->id);

        $response = $this->actingAs($admin)
            ->get(route('app.admin.audit-events.index', ['entity_type' => 'subscription', 'entity_id' => 2]))
            ->assertOk();

        $response->assertSee('sub2-marker');
        $response->assertDontSee('sub1-marker');
    }

    public function test_index_paginates_at_50_per_page(): void
    {
        $admin = User::factory()->admin()->create();
        // 60 events to confirm pagination kicks in.
        for ($i = 0; $i < 60; $i++) {
            AuditLogger::log('subscription', $i + 1, 'test_action', actorUserId: $admin->id);
        }

        $response = $this->actingAs($admin)
            ->get(route('app.admin.audit-events.index'))
            ->assertOk();

        // Pagination renders a "next" link.
        $response->assertSee('rel="next"', false);
    }

    public function test_unknown_entity_type_filter_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.audit-events.index', ['entity_type' => 'banana']))
            ->assertSessionHasErrors(['entity_type']);
    }
}
