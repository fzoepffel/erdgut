<?php

namespace Tests\Feature\Localization;

use Tests\TestCase;

/**
 * Guards against translation drift: every __() / @lang / trans() key referenced
 * in resources/views and app/ must have an entry in every lang/{locale}.json file,
 * and all three JSON files must share the same key set.
 */
class TranslationParityTest extends TestCase
{
    /**
     * Keys that are intentional prefixes for dynamic construction (e.g. __($prefix . $var)).
     * Their concrete variants (e.g. `admin.box_setup.size_small`) still have to be translated.
     */
    private const DANGLING_PREFIXES = [
        'admin.box_setup.size_',
        'admin.box_setup.status_',
        'admin.deliveries.status.',
        'admin.category.',
        'box_size.description.',
        'allergens.',
        'plan.',
        'admin.audit.entity_type.',
        'status.',
        // Spargel trial-shop view builds keys like 'spargel.type_'.$type
        // (green/white). The concrete keys are still asserted to exist.
        'spargel.type_',
        // /spargel-admin renders status badges via 'spargel.admin.status_'.$key
        // — pending/paid/fulfilled/cancelled/refunded.
        'spargel.admin.status_',
        // /app/admin/shop-orders builds status labels via
        // 'shop.admin.status_'.$status->value. Concrete variants exist.
        'shop.admin.status_',
        // Admin coupons + storefront build keys like 'coupon.type_'.$type->value
        // (percent|fixed). Concrete variants live in lang/{de,en,fr}.json.
        'coupon.type_',
        // Coupon preview maps service rejection reasons via
        // 'coupon.errors.'.$reason (invalid|min_order|limit_reached). Concrete
        // variants exist.
        'coupon.errors.',
        // EditorialContent helper picks rotation indexes at runtime; concrete
        // variants (_1.._6 etc.) live in lang/{de,en,fr}.json and the helper's
        // own unit test asserts every ISO week resolves.
        'customer.story.dashboard.weekly_intro_',
        'customer.story.dashboard.founder_note_',
        'customer.story.box.this_week_lead_',
        // Producer slug is interpolated into the key (anna|elena|jakob|...).
        'customer.producer.spotlight.',
        // Recipe slug is interpolated into the key (`customer.recipe.spargel-erdbeer.title`, etc.).
        // EditorialContent rotates through concrete recipe keys that all live in lang/{de,en,fr}.json.
        'customer.recipe.',
        // ProductUnit enum value is interpolated into the key (`units.kg`, etc.).
        'units.',
        // Onboarding layout builds hero keys like 'onboarding.hero.'.$heroKey.'.eyebrow'
        // (address|box|schedule|payment). Concrete variants live in lang/{de,en,fr}.json.
        'onboarding.hero.',
        // Restaurant (B2B) views build keys dynamically: the signup/review forms
        // use 'restaurant.field_'.$name and 'restaurant.'.$labelKey; the admin
        // account queue uses 'restaurant.status_'.$status; the order views use
        // 'restaurant.order_status_'.$status->value. All concrete variants exist.
        'restaurant.field_',
        'restaurant.',
        'restaurant.status_',
        'restaurant.order_status_',
    ];

    public function test_every_translation_key_used_in_source_exists_in_every_locale(): void
    {
        $root = base_path();
        $locales = ['de', 'en', 'fr'];
        $trans = [];
        foreach ($locales as $l) {
            $p = $root.'/lang/'.$l.'.json';
            $this->assertFileExists($p, "lang/{$l}.json is missing");
            $trans[$l] = json_decode(file_get_contents($p), true) ?? [];
        }

        $keys = $this->collectKeys([$root.'/resources/views', $root.'/app']);

        $missing = array_fill_keys($locales, []);
        foreach ($keys as $key) {
            foreach ($locales as $l) {
                if (! array_key_exists($key, $trans[$l])) {
                    $missing[$l][] = $key;
                }
            }
        }

        foreach ($locales as $l) {
            $this->assertSame(
                [],
                $missing[$l],
                "Missing keys in lang/{$l}.json:\n- ".implode("\n- ", array_slice($missing[$l], 0, 20))
                ."\n(total: ".count($missing[$l]).')'
            );
        }
    }

    public function test_lang_files_have_identical_key_sets(): void
    {
        $root = base_path();
        $de = array_keys(json_decode(file_get_contents($root.'/lang/de.json'), true) ?? []);
        $en = array_keys(json_decode(file_get_contents($root.'/lang/en.json'), true) ?? []);
        $fr = array_keys(json_decode(file_get_contents($root.'/lang/fr.json'), true) ?? []);

        $deOnly = array_values(array_diff($de, $en));
        $enOnly = array_values(array_diff($en, $de));
        $frOnly = array_values(array_diff($fr, $de));
        $deOnlyVsFr = array_values(array_diff($de, $fr));

        $this->assertSame([], $deOnly, 'Keys in de.json missing from en.json: '.implode(', ', array_slice($deOnly, 0, 10)));
        $this->assertSame([], $enOnly, 'Keys in en.json missing from de.json: '.implode(', ', array_slice($enOnly, 0, 10)));
        $this->assertSame([], $frOnly, 'Keys in fr.json missing from de.json: '.implode(', ', array_slice($frOnly, 0, 10)));
        $this->assertSame([], $deOnlyVsFr, 'Keys in de.json missing from fr.json: '.implode(', ', array_slice($deOnlyVsFr, 0, 10)));
    }

    /** @return list<string> */
    private function collectKeys(array $roots): array
    {
        $files = [];
        $walk = function (string $dir) use (&$walk, &$files): void {
            foreach (scandir($dir) as $f) {
                if ($f === '.' || $f === '..') {
                    continue;
                }
                $p = $dir.'/'.$f;
                if (is_dir($p)) {
                    $walk($p);

                    continue;
                }
                if (preg_match('/\.(blade\.php|php)$/', $f)) {
                    $files[] = $p;
                }
            }
        };
        foreach ($roots as $r) {
            $walk($r);
        }

        $found = [];
        $patterns = [
            "/__\\(\\s*(['\"])((?:\\\\.|(?!\\1).)*?)\\1/s",
            "/@lang\\(\\s*(['\"])((?:\\\\.|(?!\\1).)*?)\\1/s",
            "/trans\\(\\s*(['\"])((?:\\\\.|(?!\\1).)*?)\\1/s",
        ];
        foreach ($files as $file) {
            $src = file_get_contents($file);
            foreach ($patterns as $pat) {
                if (preg_match_all($pat, $src, $m)) {
                    foreach ($m[2] as $k) {
                        $k = stripcslashes($k);
                        if ($this->shouldIgnore($k)) {
                            continue;
                        }
                        $found[$k] = true;
                    }
                }
            }
        }

        return array_keys($found);
    }

    private function shouldIgnore(string $k): bool
    {
        if ($k === '') {
            return true;
        }
        if (preg_match('/^[0-9]{1,3}$/', $k)) {
            return true;
        }
        if (in_array($k, self::DANGLING_PREFIXES, true)) {
            return true;
        }

        return false;
    }
}
