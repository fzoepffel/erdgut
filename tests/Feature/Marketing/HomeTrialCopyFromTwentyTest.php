<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks the public marketing trial promo against drifting back to the
 * misleading "EUR 35" copy.
 *
 * The trial wizard offers all three sizes (Klein 20 € / Mittel 35 € /
 * Groß 42 €), so the homepage promo must communicate "ab EUR 20" with
 * an explicit "you pick the size" — not a single fixed price. The amount
 * derives from `box.included_value_by_size.small` so the copy auto-tracks
 * the cheapest size if pricing ever moves.
 */
class HomeTrialCopyFromTwentyTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_promo_advertises_lowest_size_price_and_size_choice(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();

        // Test suite runs with APP_LOCALE=en. The new copy starts with
        // "From EUR" and explicitly mentions size choice.
        $response->assertSee('From EUR', false);
        $response->assertSee('you pick the size and date', false);

        // The promoted amount is the cheapest box (Klein), not the legacy
        // trial_price_oneoff_ttc value.
        $small = (int) config('box.included_value_by_size.small', 20);
        $response->assertSee('From EUR '.$small, false);

        // Reassurance copy from the original line stays intact (no recurring
        // billing, no stored card).
        $response->assertSee('no subscription', false);
        $response->assertSee('no stored card', false);
    }

    public function test_trial_promo_does_not_promise_a_fixed_thirty_five_eur_price(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();

        $html = $response->getContent();

        // The promo must not advertise the medium-only flat price as if it
        // were the only option.
        $this->assertStringNotContainsString(
            'EUR 35 for a single Erdgut box',
            $html,
            'Trial promo should not advertise a fixed 35 € price.'
        );
    }
}
