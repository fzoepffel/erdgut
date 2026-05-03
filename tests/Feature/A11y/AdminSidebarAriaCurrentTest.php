<?php

namespace Tests\Feature\A11y;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarAriaCurrentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The admin sidebar's active nav link must carry aria-current="page" so
     * screen readers announce the user's location. Guards the
     * <x-admin.nav-link> component wiring in admin-sidebar.blade.php.
     */
    public function test_active_admin_sidebar_link_has_aria_current_page(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->get('/app/admin');
        $response->assertOk();

        $html = $response->getContent();

        // Exactly one link should be marked active on the dashboard route.
        // We don't pin to a specific count (footer/account links could grow)
        // but we DO assert the dashboard "Overview" link has it set.
        preg_match_all('/<a\b[^>]*aria-current="page"[^>]*>(.*?)<\/a>/s', $html, $matches);
        $activeLinks = $matches[1] ?? [];

        $this->assertNotEmpty(
            $activeLinks,
            'expected at least one nav link with aria-current="page" on /app/admin'
        );

        $hasOverview = collect($activeLinks)->contains(
            fn ($label) => str_contains($label, __('admin.shell.overview'))
        );
        $this->assertTrue(
            $hasOverview,
            'expected the Overview nav link to be marked aria-current="page" on the admin dashboard'
        );
    }
}
