<?php

// Scans all .blade.php views and .php files under app/, resources/views/, lang/
// Reports untranslated keys per lang file and counts per view.

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

// Dangling string literals used as dynamic key prefixes in source (e.g. __($prefix . $var)).
$danglingPrefixes = [
    'admin.box_setup.size_',
    'admin.box_setup.status_',
    'admin.deliveries.status.',
    'admin.category.',
    'box_size.description.',
];
$ignoreKey = function (string $k) use ($danglingPrefixes): bool {
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

$keysPerFile = [];
$allKeys = [];
foreach ($files as $file) {
    $src = file_get_contents($file);
    $pattern = "/__\\(\\s*(['\\\"])((?:\\\\.|(?!\\1).)*?)\\1/s";
    $all = [];
    if (preg_match_all($pattern, $src, $m)) {
        $all = array_merge($all, $m[2]);
    }
    if (preg_match_all('/@lang\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s', $src, $m)) {
        $all = array_merge($all, $m[2]);
    }
    if (preg_match_all('/trans\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1/s', $src, $m)) {
        $all = array_merge($all, $m[2]);
    }
    foreach ($all as $k) {
        $k = stripcslashes($k);
        if ($ignoreKey($k)) {
            continue;
        }
        $keysPerFile[$file][$k] = true;
        $allKeys[$k] = true;
    }
}

echo "=== SUMMARY ===\n";
echo 'Total unique __() keys discovered: '.count($allKeys)."\n";
foreach ($locales as $l) {
    $missing = [];
    foreach (array_keys($allKeys) as $k) {
        if (! array_key_exists($k, $trans[$l])) {
            $missing[] = $k;
        }
    }
    echo sprintf("  Missing in %s.json: %d / %d\n", $l, count($missing), count($allKeys));
}

echo "\n=== MISSING BY FILE (de.json) ===\n";
foreach ($keysPerFile as $file => $keys) {
    $miss = [];
    foreach (array_keys($keys) as $k) {
        if (! array_key_exists($k, $trans['de'])) {
            $miss[] = $k;
        }
    }
    if ($miss) {
        echo "\n-- ".str_replace($root.'/', '', $file).' ('.count($miss).' missing) --'."\n";
        foreach ($miss as $k) {
            echo '  - '.substr($k, 0, 120)."\n";
        }
    }
}

echo "\n=== MISSING BY FILE (fr.json) ===\n";
foreach ($keysPerFile as $file => $keys) {
    $miss = [];
    foreach (array_keys($keys) as $k) {
        if (! array_key_exists($k, $trans['fr'])) {
            $miss[] = $k;
        }
    }
    if ($miss) {
        echo "\n-- ".str_replace($root.'/', '', $file).' ('.count($miss).' missing) --'."\n";
        foreach ($miss as $k) {
            echo '  - '.substr($k, 0, 120)."\n";
        }
    }
}
