<?php

// Emits JSON: { file: [missing_keys_for_de, ...], allMissingDe: [...], allMissingFr: [...], allMissingEn: [...], allKeys: [...] }

$root = dirname(__DIR__);

$files = [];
$iter = function ($dir) use (&$iter, &$files) {
    foreach (scandir($dir) as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $path = $dir.'/'.$f;
        if (is_dir($path)) {
            $iter($path);

            continue;
        }
        if (preg_match('/\.(blade\.php|php)$/', $f)) {
            $files[] = $path;
        }
    }
};
$iter($root.'/resources/views');
$iter($root.'/app');

$locales = ['de', 'en', 'fr'];
$trans = [];
foreach ($locales as $l) {
    $p = $root."/lang/$l.json";
    $trans[$l] = file_exists($p) ? json_decode(file_get_contents($p), true) : [];
}

$danglingPrefixes = [
    'admin.box_setup.size_',
    'admin.box_setup.status_',
    'admin.deliveries.status.',
    'admin.category.',
    'box_size.description.',
];
$ignore = function (string $k) use ($danglingPrefixes): bool {
    if ($k === '') {
        return true;
    }
    if (preg_match('/^[0-9]{1,3}$/', $k)) {
        return true;
    }
    if (in_array($k, $danglingPrefixes, true)) {
        return true;
    }

    return false;
};

$perFile = [];
$allKeys = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    $patterns = [
        "/__\\(\\s*(['\\\"])((?:\\\\.|(?!\\1).)*?)\\1/s",
        "/@lang\\(\\s*(['\\\"])((?:\\\\.|(?!\\1).)*?)\\1/s",
        "/trans\\(\\s*(['\\\"])((?:\\\\.|(?!\\1).)*?)\\1/s",
    ];
    foreach ($patterns as $pat) {
        if (preg_match_all($pat, $src, $m)) {
            foreach ($m[2] as $k) {
                $k = stripcslashes($k);
                if ($ignore($k)) {
                    continue;
                }
                $perFile[$file][$k] = true;
                $allKeys[$k] = true;
            }
        }
    }
}

$out = ['files' => [], 'summary' => []];
foreach ($perFile as $file => $keys) {
    $rel = str_replace($root.'/', '', $file);
    $de = $en = $fr = [];
    foreach (array_keys($keys) as $k) {
        if (! array_key_exists($k, $trans['de'])) {
            $de[] = $k;
        }
        if (! array_key_exists($k, $trans['en'])) {
            $en[] = $k;
        }
        if (! array_key_exists($k, $trans['fr'])) {
            $fr[] = $k;
        }
    }
    if ($de || $en || $fr) {
        $out['files'][$rel] = ['missing_de' => $de, 'missing_en' => $en, 'missing_fr' => $fr];
    }
}
$missDe = $missEn = $missFr = [];
foreach (array_keys($allKeys) as $k) {
    if (! array_key_exists($k, $trans['de'])) {
        $missDe[] = $k;
    }
    if (! array_key_exists($k, $trans['en'])) {
        $missEn[] = $k;
    }
    if (! array_key_exists($k, $trans['fr'])) {
        $missFr[] = $k;
    }
}
$out['summary'] = [
    'total_keys' => count($allKeys),
    'missing_de' => $missDe,
    'missing_en' => $missEn,
    'missing_fr' => $missFr,
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
