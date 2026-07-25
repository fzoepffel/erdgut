<?php

namespace App\Contracts;

/**
 * Stores a copy of an already-sent message so it appears in a mailbox's
 * "Sent" folder. SMTP submission never does this on its own.
 */
interface SentMailArchive
{
    /**
     * Save the raw MIME of a sent message into the Sent folder of the mailbox
     * it was sent from. Implementations are best-effort: an unknown/disabled
     * sender or a transport failure must not throw — outbound mail already
     * left, and archiving is never allowed to break a send.
     *
     * @param  string  $fromAddress  The message's From address (case-insensitive).
     * @param  string  $rawMessage  The full RFC 5322 MIME string of the sent mail.
     * @return bool True when a copy was stored, false when skipped.
     */
    public function store(string $fromAddress, string $rawMessage): bool;
}
