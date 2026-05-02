<?php

namespace Tests\Unit\Support;

use App\Models\Box;
use App\Support\EditorialContent;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EditorialContentTest extends TestCase
{
    public function test_weekly_intro_resolves_for_every_iso_week(): void
    {
        for ($week = 1; $week <= 53; $week++) {
            $when = Carbon::now()->startOfYear()->addWeeks($week - 1);
            $resolved = EditorialContent::weeklyIntro($when);

            $this->assertNotEmpty($resolved, "Week {$week} weekly_intro is empty");
            $this->assertStringNotContainsString(
                'customer.story.dashboard.weekly_intro_',
                $resolved,
                "Week {$week} weekly_intro returned the unresolved key"
            );
        }
    }

    public function test_founder_note_resolves_for_every_iso_week(): void
    {
        for ($week = 1; $week <= 53; $week++) {
            $when = Carbon::now()->startOfYear()->addWeeks($week - 1);
            $resolved = EditorialContent::founderNote($when);

            $this->assertNotEmpty($resolved, "Week {$week} founder_note is empty");
            $this->assertStringNotContainsString(
                'customer.story.dashboard.founder_note_',
                $resolved,
                "Week {$week} founder_note returned the unresolved key"
            );
        }
    }

    public function test_this_week_lead_resolves_for_box_with_week_start(): void
    {
        $box = new Box(['week_start_date' => Carbon::parse('2026-05-04')]);
        $resolved = EditorialContent::thisWeekLead($box);

        $this->assertNotEmpty($resolved);
        $this->assertStringNotContainsString('customer.story.box.this_week_lead_', $resolved);
    }

    public function test_this_week_lead_resolves_when_box_is_null(): void
    {
        $resolved = EditorialContent::thisWeekLead(null);

        $this->assertNotEmpty($resolved);
        $this->assertStringNotContainsString('customer.story.box.this_week_lead_', $resolved);
    }

    public function test_producer_of_the_week_returns_a_known_slug(): void
    {
        $producer = EditorialContent::producerOfTheWeek();

        $this->assertContains($producer['slug'], EditorialContent::PRODUCER_SLUGS);
        $this->assertNotEmpty($producer['name']);
        $this->assertNotEmpty($producer['role']);
        $this->assertNotEmpty($producer['bio']);
        $this->assertStringContainsString('mock/producers/', $producer['photo']);
    }

    public function test_producer_rotates_across_iso_weeks(): void
    {
        $seen = [];
        for ($week = 1; $week <= 6; $week++) {
            $when = Carbon::now()->startOfYear()->addWeeks($week - 1);
            $seen[] = EditorialContent::producerOfTheWeek($when)['slug'];
        }

        $this->assertSame(EditorialContent::PRODUCER_SLUGS, $seen);
    }

    public function test_every_producer_slug_resolves_to_translated_strings(): void
    {
        foreach (EditorialContent::PRODUCER_SLUGS as $slug) {
            $producer = EditorialContent::producer($slug);

            $this->assertNotEmpty($producer['name'], "Producer {$slug} has no name");
            $this->assertNotEmpty($producer['role'], "Producer {$slug} has no role");
            $this->assertNotEmpty($producer['bio'], "Producer {$slug} has no bio");
            $this->assertStringNotContainsString(
                'customer.producer.spotlight.',
                $producer['name'].$producer['role'].$producer['bio'],
                "Producer {$slug} has unresolved key"
            );
        }
    }
}
