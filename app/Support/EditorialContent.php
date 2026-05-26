<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Box;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Mock storytelling content for the logged-in customer surfaces.
 *
 * The redesign aligns logged-in pages with the marketing homepage's
 * editorial voice. Until real producer copy + photography land, this
 * helper rotates a small bank of invented blurbs by ISO week (or by
 * box id) so the same customer sees fresh content week to week.
 *
 * Every method returns translation keys (resolved via __()) so de/en/fr
 * stay in parity. When real content arrives, swap the JSON values; the
 * call sites do not change.
 */
final class EditorialContent
{
    public const PRODUCER_SLUGS = ['anna', 'elena', 'jakob', 'marcus', 'sophia', 'thomas'];

    /**
     * Static bank of recipes that rotate by ISO week, same pattern as
     * {@see producerOfTheWeek()}. v1 is editorial-only — the admin UI to
     * curate recipes per week lands in ERD-358.
     *
     * `ingredient_slugs` are lowercase Latin tokens used to match against
     * box-item product names (substring/lowercased compare) so the
     * dashboard can render "Aus der Box X von Y" without needing a
     * products.slug column. When real product slugs land, the matcher
     * can be tightened to direct slug equality.
     *
     * `photo` reuses an existing stall scene asset until proper recipe
     * photography is uploaded.
     *
     * @var array<int, array{slug:string,photo:string,ingredient_slugs:array<int, string>}>
     */
    private const RECIPE_BANK = [
        ['slug' => 'spargel-erdbeer', 'photo' => 'images/mock/stalls/produce/scene.jpg', 'ingredient_slugs' => ['spargel', 'erdbeer', 'radieschen', 'ricotta']],
        ['slug' => 'tomaten-brot', 'photo' => 'images/mock/stalls/bakery/scene.jpg', 'ingredient_slugs' => ['tomaten', 'brot', 'gurke', 'basilikum']],
        ['slug' => 'wurzelgemuese', 'photo' => 'images/mock/stalls/produce/scene.jpg', 'ingredient_slugs' => ['karotten', 'rote bete', 'pastinaken', 'thymian']],
        ['slug' => 'pilzragout', 'photo' => 'images/mock/stalls/bakery/scene.jpg', 'ingredient_slugs' => ['pilze', 'sauerteigbrot', 'zwiebel', 'thymian']],
        ['slug' => 'kuerbis-apfel-suppe', 'photo' => 'images/mock/stalls/produce/scene.jpg', 'ingredient_slugs' => ['kürbis', 'apfel', 'ingwer', 'kokosmilch']],
        ['slug' => 'frischkaese-kraeuter', 'photo' => 'images/mock/stalls/dairy/scene.jpg', 'ingredient_slugs' => ['frischkäse', 'kräuter', 'mehl', 'butter']],
    ];

    /**
     * Lowercase slugs of every recipe in the rotation bank.
     *
     * @return array<int, string>
     */
    public static function recipeSlugs(): array
    {
        return array_column(self::RECIPE_BANK, 'slug');
    }

    /**
     * Short seasonal intro for the dashboard hero, ≤90 words.
     * Rotates across 6 variants by ISO week.
     */
    public static function weeklyIntro(?CarbonInterface $when = null): string
    {
        $week = ($when ?? Carbon::now())->isoWeek();
        $index = (($week - 1) % 6) + 1;

        return __('customer.story.dashboard.weekly_intro_'.$index);
    }

    /**
     * One-line founder note shown on the dashboard's "this week's story" band.
     * Rotates across 4 variants by ISO week.
     */
    public static function founderNote(?CarbonInterface $when = null): string
    {
        $week = ($when ?? Carbon::now())->isoWeek();
        $index = (($week - 1) % 4) + 1;

        return __('customer.story.dashboard.founder_note_'.$index);
    }

    /**
     * "This week in the box" seasonal lead, keyed by the box's week-start
     * date so it stays stable for a given box.
     */
    public static function thisWeekLead(?Box $box = null): string
    {
        if ($box?->week_start_date instanceof CarbonInterface) {
            $week = $box->week_start_date->isoWeek();
        } else {
            $week = Carbon::now()->isoWeek();
        }
        $index = (($week - 1) % 5) + 1;

        return __('customer.story.box.this_week_lead_'.$index);
    }

    /**
     * Producer spotlight entry — rotates by ISO week so a customer sees
     * one producer this week, another next week.
     *
     * @return array{slug:string,name:string,role:string,bio:string,photo:string}
     */
    public static function producerOfTheWeek(?CarbonInterface $when = null): array
    {
        $week = ($when ?? Carbon::now())->isoWeek();
        $slug = self::PRODUCER_SLUGS[($week - 1) % count(self::PRODUCER_SLUGS)];

        return self::producer($slug);
    }

    /**
     * @return array{slug:string,name:string,role:string,bio:string,quote:string,photo:string}
     */
    public static function producer(string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => __('customer.producer.spotlight.'.$slug.'.name'),
            'role' => __('customer.producer.spotlight.'.$slug.'.role'),
            'bio' => __('customer.producer.spotlight.'.$slug.'.bio'),
            // Short editorial quote used by the landscape <x-customer.producer-feature>
            // on the dashboard. Falls back to the bio when no quote key is configured
            // (e.g. for any new slug added before its quote translation lands).
            'quote' => __('customer.producer.spotlight.'.$slug.'.quote'),
            'photo' => asset('images/mock/producers/'.$slug.'.jpg'),
        ];
    }

    /**
     * Recipe pick for the dashboard's "kitchen suggestion" section.
     * Rotates by ISO week through {@see RECIPE_BANK}.
     *
     * @return array{slug:string,title:string,description:string,time_minutes:int,serves:int,ingredient_slugs:array<int, string>,photo:string}
     */
    public static function recipeOfTheWeek(?CarbonInterface $when = null): array
    {
        $week = ($when ?? Carbon::now())->isoWeek();
        $index = ($week - 1) % count(self::RECIPE_BANK);
        $entry = self::RECIPE_BANK[$index];

        return self::recipe($entry['slug']);
    }

    /**
     * Resolve a recipe payload by slug. Returns null when the slug is
     * unknown so the controller can 404 cleanly.
     *
     * @return array{slug:string,title:string,description:string,time_minutes:int,serves:int,ingredient_slugs:array<int, string>,photo:string}|null
     */
    public static function recipe(string $slug): ?array
    {
        $entry = collect(self::RECIPE_BANK)->firstWhere('slug', $slug);
        if ($entry === null) {
            return null;
        }

        return [
            'slug' => $entry['slug'],
            'title' => __('customer.recipe.'.$slug.'.title'),
            'description' => __('customer.recipe.'.$slug.'.description'),
            'time_minutes' => (int) __('customer.recipe.'.$slug.'.time_minutes'),
            'serves' => (int) __('customer.recipe.'.$slug.'.serves'),
            'ingredient_slugs' => $entry['ingredient_slugs'],
            'photo' => asset($entry['photo']),
        ];
    }
}
