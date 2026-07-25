<?php

namespace App\Support;

use App\Contracts\SentMailArchive;
use RuntimeException;

/**
 * Appends a copy of each sent message to the IMAP "Sent" folder of the
 * mailbox it was sent from, via the native imap_* extension.
 *
 * Best-effort by contract: a disabled archive, an unknown sender, a missing
 * imap extension, or an IMAP failure all return false rather than throw, so a
 * hiccup here never affects the outbound send that already happened.
 */
class ImapSentMailArchive implements SentMailArchive
{
    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param  array<string, mixed>|null  $config  Defaults to config('mail.archive').
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) config('mail.archive', []);
    }

    public function store(string $fromAddress, string $rawMessage): bool
    {
        if (empty($this->config['enabled'])) {
            return false;
        }

        $account = $this->resolveAccount($fromAddress);
        if ($account === null) {
            return false;
        }

        if (! function_exists('imap_open')) {
            // Extension not installed on this host: skip silently. Logged by
            // the caller's report() only if something worse happens.
            return false;
        }

        $host = (string) ($this->config['host'] ?? 'imap.ionos.de');
        $port = (int) ($this->config['port'] ?? 993);
        $folder = (string) ($this->config['folder'] ?? 'Sent');
        $timeout = (int) ($this->config['timeout'] ?? 15);

        $mailbox = '{'.$host.':'.$port.'/imap/ssl}'.$folder;

        // IMAP APPEND expects CRLF line endings; Symfony already emits them,
        // but normalise defensively so a bare-LF message is still accepted.
        $message = preg_replace('/\r\n|\r|\n/', "\r\n", $rawMessage);

        imap_timeout(IMAP_OPENTIMEOUT, max(1, $timeout));
        imap_timeout(IMAP_READTIMEOUT, max(1, $timeout));
        imap_timeout(IMAP_WRITETIMEOUT, max(1, $timeout));

        $connection = null;

        try {
            // OP_HALFOPEN: connect/authenticate without selecting a mailbox —
            // we only need the server session to run APPEND. One retry, no
            // background reconnection loop.
            $connection = @imap_open($mailbox, (string) $account['username'], (string) $account['password'], OP_HALFOPEN, 1);

            if ($connection === false) {
                throw new RuntimeException('IMAP connect failed: '.($this->lastError() ?: 'unknown error'));
            }

            $appended = @imap_append($connection, $mailbox, $message, '\\Seen');

            if ($appended === false) {
                throw new RuntimeException('IMAP append failed: '.($this->lastError() ?: 'unknown error'));
            }

            return true;
        } finally {
            if (is_object($connection) || is_resource($connection)) {
                @imap_close($connection);
            }
            // Drain any queued warnings so they don't surface on a later call.
            imap_errors();
            imap_alerts();
        }
    }

    /**
     * The IMAP login for a given From address, or null when that sender has
     * no configured mailbox.
     *
     * @return array{username: string, password: string}|null
     */
    public function resolveAccount(string $fromAddress): ?array
    {
        $accounts = (array) ($this->config['accounts'] ?? []);
        $key = strtolower(trim($fromAddress));

        $account = $accounts[$key] ?? null;
        if (! is_array($account) || empty($account['password']) || empty($account['username'])) {
            return null;
        }

        return [
            'username' => (string) $account['username'],
            'password' => (string) $account['password'],
        ];
    }

    private function lastError(): string
    {
        $error = function_exists('imap_last_error') ? imap_last_error() : '';

        return is_string($error) ? $error : '';
    }
}
