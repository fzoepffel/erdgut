<?php

namespace App\Services\Admin;

/**
 * Single operational notice for the admin dashboard (translation keys + link).
 */
final readonly class AdminOperationalAlert
{
    /**
     * @param  array<string, string|int>  $messageParams
     */
    public function __construct(
        public string $severity,
        public string $messageKey,
        public array $messageParams,
        public string $href,
        public string $linkTextKey,
    ) {}
}
