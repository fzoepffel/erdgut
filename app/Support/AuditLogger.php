<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Records business-state transitions to the audit_events table.
 *
 * Intended for non-trivial actions that admins or users need to inspect later:
 * status changes, manual charges, permission overrides, etc. Writes are
 * best-effort: failures are logged but never bubble up to the caller, so audit
 * logging cannot break the primary flow.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function log(
        string $entityType,
        int $entityId,
        string $action,
        ?string $fromState = null,
        ?string $toState = null,
        ?string $reason = null,
        array $metadata = [],
        ?int $actorUserId = null,
    ): ?AuditEvent {
        try {
            return AuditEvent::create([
                'actor_user_id' => $actorUserId ?? Auth::id(),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'from_state' => $fromState,
                'to_state' => $toState,
                'reason' => $reason,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('AuditLogger: failed to record event.', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'exception_class' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
