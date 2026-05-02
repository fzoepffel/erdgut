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
     * @return array{slug:string,name:string,role:string,bio:string,photo:string}
     */
    public static function producer(string $slug): array
    {
        return [
            'slug' => $slug,
            'name' => __('customer.producer.spotlight.'.$slug.'.name'),
            'role' => __('customer.producer.spotlight.'.$slug.'.role'),
            'bio' => __('customer.producer.spotlight.'.$slug.'.bio'),
            'photo' => asset('images/mock/producers/'.$slug.'.jpg'),
        ];
    }
}
