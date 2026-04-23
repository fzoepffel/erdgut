<?php

/**
 * Generate the Open Graph / Twitter Card share image for erd-gut.de.
 *
 * Composition (1200×630, Facebook/Twitter recommended size):
 *   - Olive chapter-produce-soft background (#709452)
 *   - Cabbage illustration anchored to the left edge, full height
 *   - ERDGUT wordmark rendered in dark chapter-produce (#21341d) on the right
 *   - Subtle triple-asterisk accent in meat-red (#c53a2b) above the wordmark
 *
 * Run once locally; commit the resulting PNG. Re-run only when the visual
 * identity changes.
 *
 *   php scripts/generate-og-image.php
 */

// ----- Canvas -----
$W = 1200;
$H = 630;
$canvas = imagecreatetruecolor($W, $H);
imagealphablending($canvas, true);
imagesavealpha($canvas, true);

// Fill bg: chapter-produce-soft #709452
$bg = imagecolorallocate($canvas, 0x70, 0x94, 0x52);
imagefilledrectangle($canvas, 0, 0, $W, $H, $bg);

// ----- Cabbage stripe (left) -----
// Source image is pre-cropped to cabbage-only (~571x3509, very tall).
// Drop it into a left-hand column ~360px wide, scaled to fill full height.
$cabbageSrc = imagecreatefrompng(__DIR__.'/../public/images/chapters/cabbage.png');
$srcW = imagesx($cabbageSrc);
$srcH = imagesy($cabbageSrc);

$cabbageColW = 360;
// Use object-contain-like sizing: fit height, then width follows aspect.
$scale = $H / $srcH;
$dstW = (int) round($srcW * $scale);
$dstH = $H;
// Anchor to left, vertically centered (will be full height anyway).
$dstX = 0;
$dstY = 0;

// Preserve alpha when copying PNG onto canvas.
imagecopyresampled(
    $canvas, $cabbageSrc,
    $dstX, $dstY, 0, 0,
    $dstW, $dstH, $srcW, $srcH
);

// ----- Wordmark (right column) -----
// The tight-bbox wordmark PNG has its glyphs as opaque pixels in a shade
// that happens to match the page background, so compositing it directly
// would make it invisible. We re-render it using only the alpha channel,
// filled in dark chapter-produce green.
$wordmarkSrc = imagecreatefrompng(__DIR__.'/../public/images/logo/erdgut-wordmark-tight.png');
$wmSrcW = imagesx($wordmarkSrc);
$wmSrcH = imagesy($wordmarkSrc);

// Aspect ~4.9:1. Target width 720px → height 147px. Fits comfortably in the
// right column (which starts at x=360 and ends at x=1200 → 840px wide).
$wmTargetW = 720;
$wmTargetH = (int) round($wmSrcH * ($wmTargetW / $wmSrcW));

// Build a transparent intermediate buffer at target size, draw dark-green
// pixels where the source is opaque, then copy onto the canvas.
$wmBuf = imagecreatetruecolor($wmTargetW, $wmTargetH);
imagealphablending($wmBuf, false);
imagesavealpha($wmBuf, true);
$transparent = imagecolorallocatealpha($wmBuf, 0, 0, 0, 127);
imagefilledrectangle($wmBuf, 0, 0, $wmTargetW, $wmTargetH, $transparent);

// Resize source to target size first so alpha mapping is cheap.
$wmScaled = imagecreatetruecolor($wmTargetW, $wmTargetH);
imagealphablending($wmScaled, false);
imagesavealpha($wmScaled, true);
imagefilledrectangle($wmScaled, 0, 0, $wmTargetW, $wmTargetH, $transparent);
imagecopyresampled(
    $wmScaled, $wordmarkSrc,
    0, 0, 0, 0,
    $wmTargetW, $wmTargetH, $wmSrcW, $wmSrcH
);

$darkR = 0x21;
$darkG = 0x34;
$darkB = 0x1D;
for ($y = 0; $y < $wmTargetH; $y++) {
    for ($x = 0; $x < $wmTargetW; $x++) {
        $c = imagecolorat($wmScaled, $x, $y);
        $alpha = ($c >> 24) & 0xFF; // 0 = opaque, 127 = fully transparent
        if ($alpha < 127) {
            $newColor = imagecolorallocatealpha($wmBuf, $darkR, $darkG, $darkB, $alpha);
            imagesetpixel($wmBuf, $x, $y, $newColor);
        }
    }
}

// Position wordmark: horizontally anchored to the right column, vertically
// slightly above centre so the asterisks + tagline feel weighted.
$wmX = $cabbageColW + 80; // 440
$wmY = (int) (($H - $wmTargetH) / 2) - 40; // roughly vertically centred, slightly up
imagealphablending($canvas, true);
imagecopy($canvas, $wmBuf, $wmX, $wmY, 0, 0, $wmTargetW, $wmTargetH);

// ----- Triple-asterisk accent (above wordmark) -----
// Draw three simple 8-point stars in meat-red #c53a2b.
$starColor = imagecolorallocate($canvas, 0xC5, 0x3A, 0x2B);
$starY = $wmY - 46;
$starSpacing = 28;
$starCx = $wmX + 12;
$starSize = 8;
for ($i = 0; $i < 3; $i++) {
    $cx = $starCx + $i * $starSpacing;
    // simple cross + diagonal (approximates an 8-point asterisk)
    imagefilledrectangle($canvas, $cx - $starSize, $starY - 1, $cx + $starSize, $starY + 1, $starColor);
    imagefilledrectangle($canvas, $cx - 1, $starY - $starSize, $cx + 1, $starY + $starSize, $starColor);
    // diagonals (approximated as thick line)
    for ($d = -$starSize; $d <= $starSize; $d++) {
        imagesetpixel($canvas, $cx + $d, $starY + $d, $starColor);
        imagesetpixel($canvas, $cx + $d, $starY - $d, $starColor);
    }
}

// ----- Tagline (below wordmark) -----
// Use a system TTF for a tasteful serif italic tagline — render at high
// resolution so it stays crisp at the final PNG size.
$fontPath = null;
$fontCandidates = [
    '/System/Library/Fonts/Supplemental/Georgia Italic.ttf',
    '/Library/Fonts/Georgia Italic.ttf',
    '/System/Library/Fonts/Supplemental/Times New Roman Italic.ttf',
    '/System/Library/Fonts/Supplemental/Arial Italic.ttf',
];
foreach ($fontCandidates as $candidate) {
    if (is_readable($candidate)) {
        $fontPath = $candidate;
        break;
    }
}

if ($fontPath) {
    $taglineColor = imagecolorallocatealpha($canvas, 0x21, 0x34, 0x1D, 40); // softer
    $tagline = 'Frisches Gemüse aus Berliner Bauernhand.';
    $taglineSize = 28;
    $taglineY = $wmY + $wmTargetH + 70;
    imagettftext($canvas, $taglineSize, 0, $wmX + 6, $taglineY, $taglineColor, $fontPath, $tagline);
} else {
    fwrite(STDERR, "Warning: no suitable italic TTF found; OG image generated without tagline.\n");
}

// ----- Save -----
$outPath = __DIR__.'/../public/images/og/erdgut-og-default.png';
imagepng($canvas, $outPath, 6); // compression level 6

echo "Wrote OG image: $outPath\n";
echo "Dimensions: {$W}×{$H}\n";
echo 'File size: '.number_format(filesize($outPath))." bytes\n";
