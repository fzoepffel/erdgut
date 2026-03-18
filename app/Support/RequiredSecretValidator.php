<?php

namespace App\Support;

class RequiredSecretValidator
{
    /**
     * Return missing/invalid secret keys for staging or production.
     *
     * @param  array<string, mixed>  $secrets
     * @return list<string>
     */
    public function missingForEnvironment(string $environment, array $secrets): array
    {
        if (! in_array($environment, ['staging', 'production'], true)) {
            return [];
        }

        $required = [
            'APP_KEY' => $secrets['APP_KEY'] ?? null,
            'CRON_KEY' => $secrets['CRON_KEY'] ?? null,
        ];

        $missing = [];

        foreach ($required as $key => $value) {
            if (! $this->isSecureValue($key, $value)) {
                $missing[] = $key;
            }
        }

        return $missing;
    }

    private function isSecureValue(string $key, mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $normalized = trim($value);
        if ($normalized === '') {
            return false;
        }

        if ($key === 'CRON_KEY' && $normalized === 'change-this-32-char-secret') {
            return false;
        }

        return true;
    }
}
