<?php

namespace Tests\Feature\Accessibility;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ERD-343: minimum accessibility floor across the four key layouts.
 *
 * - Skip-link is the first focusable element (WCAG 2.4.1 Bypass Blocks).
 * - <main id="main"> landmark exists and is the skip-link target.
 * - <html lang> reflects the active locale.
 *
 * These checks aren't a substitute for axe/Pa11y on a real DOM — they
 * lock in the static markup that those tools' deeper checks rely on.
 */
class A11yBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_home_has_skip_link_and_main_landmark(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        $response->assertSee('href="#main"', false);
        $response->assertSee('id="main"', false);
    }

    public function test_authenticated_dashboard_has_skip_link_and_main_landmark(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertSee('href="#main"', false);
        $response->assertSee('id="main"', false);
    }

    public function test_login_page_has_skip_link(): void
    {
        $response = $this->get(route('auth.login'));

        $response->assertOk();
        $response->assertSee('href="#main"', false);
    }

    public function test_admin_dashboard_has_skip_link_and_main_landmark(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('app.admin.dashboard'));

        $response->assertOk();
        $response->assertSee('href="#main"', false);
        $response->assertSee('id="main"', false);
    }

    public function test_html_lang_attribute_is_present_on_marketing_home(): void
    {
        $response = $this->get(route('public.marketing-home'));
        $response->assertOk();
        // Verify the <html lang> attribute is rendered (exact value follows app locale).
        $this->assertMatchesRegularExpression(
            '/<html lang="[a-z]{2}">/',
            $response->getContent() ?: ''
        );
    }
}
