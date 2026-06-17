<?php

namespace App\Support;

/**
 * Normalize human-written copy that was accidentally JSON- or HTML-escaped.
 *
 * Typical corruption: pasting LLM/API JSON into a textarea stores literal
 * sequences like \u0027 instead of an apostrophe, or &#039; instead of '.
 */
final class HumanText
{
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $normalized = $text;

        for ($pass = 0; $pass < 3; $pass++) {
            $next = self::decodeJsonUnicodeEscapes($normalized);
            $next = html_entity_decode($next, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($next === $normalized) {
                break;
            }

            $normalized = $next;
        }

        return $normalized;
    }

    public static function needsNormalization(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        return self::normalize($text) !== $text;
    }

    private static function decodeJsonUnicodeEscapes(string $text): string
    {
        return preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $matches): string => mb_chr((int) hexdec($matches[1]), 'UTF-8'),
            $text
        ) ?? $text;
    }
}
