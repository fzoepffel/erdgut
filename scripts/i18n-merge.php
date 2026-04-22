<?php

// Merges translation dictionaries into lang/{de,en,fr}.json.
// Keeps existing entries. Prefers to keep original values — only inserts keys not already present.

$root = dirname(__DIR__);
$full = array_merge(
    require $root.'/scripts/i18n-translations.php',
    require $root.'/scripts/i18n-translations-part2.php',
);
$enExtra = array_merge(
    require $root.'/scripts/i18n-translations-en.php',
    require $root.'/scripts/i18n-translations-en-part2.php',
);

$load = fn (string $p) => file_exists($p) ? json_decode(file_get_contents($p), true) : [];
$save = function (string $p, array $data): void {
    // Keep key order stable: sort alphabetically by key
    ksort($data, SORT_STRING);
    file_put_contents($p, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
};

$de = $load($root.'/lang/de.json');
$en = $load($root.'/lang/en.json');
$fr = $load($root.'/lang/fr.json');

$added = ['de' => 0, 'en' => 0, 'fr' => 0];
foreach ($full as $key => $t) {
    if (! array_key_exists($key, $de)) {
        $de[$key] = $t['de'];
        $added['de']++;
    }
    if (! array_key_exists($key, $en)) {
        $en[$key] = $t['en'];
        $added['en']++;
    }
    if (! array_key_exists($key, $fr)) {
        $fr[$key] = $t['fr'];
        $added['fr']++;
    }
}
foreach ($enExtra as $key => $val) {
    if (! array_key_exists($key, $en)) {
        $en[$key] = $val;
        $added['en']++;
    }
}

// Final parity pass: every key present in de or fr but missing from en
// gets an English value equal to the key (safe for English-phrase keys; no
// semantic/dotted keys remain in this set — assert & fail loudly if any slip through).
foreach (array_keys(array_merge($de, $fr)) as $key) {
    if (! array_key_exists($key, $en)) {
        // Guard: semantic keys (with a dotted or underscored prefix before any space)
        // must not be mass-filled with the key as its English value. Bail so we
        // remember to translate them explicitly.
        if (preg_match('/^[a-z][a-z0-9]*\.[a-z0-9_.]+$/i', $key)) {
            fwrite(STDERR, "Refusing to auto-fill semantic key with its own name: $key\n");
            exit(1);
        }
        $en[$key] = $key;
        $added['en']++;
    }
}

$save($root.'/lang/de.json', $de);
$save($root.'/lang/en.json', $en);
$save($root.'/lang/fr.json', $fr);

echo 'Added entries: de='.$added['de'].' en='.$added['en'].' fr='.$added['fr']."\n";
echo 'Totals: de='.count($de).' en='.count($en).' fr='.count($fr)."\n";
