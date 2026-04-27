<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Read-only viewer for the {@see AuditEvent} stream that's already being
 * written by every lifecycle service + admin controller. Until now there
 * was no UI surface for it; support had to query the DB to reconstruct
 * "when did delivery #5 actually get marked delivered, and by whom?".
 */
class AdminAuditEventController extends Controller
{
    /**
     * Entity types we currently emit audit events for. Used to drive the
     * filter dropdown — keep aligned with AuditLogger call sites.
     */
    private const ENTITY_TYPES = [
        'subscription',
        'delivery',
        'order',
        'payment',
        'box',
        'box_item',
        'user',
    ];

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'entity_type' => ['nullable', 'string', Rule::in(self::ENTITY_TYPES)],
            'entity_id' => ['nullable', 'integer', 'min:1'],
            'action' => ['nullable', 'string', 'max:64'],
            'actor_user_id' => ['nullable', 'integer', 'min:1'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $query = AuditEvent::query()->with('actor:id,name,email');

        if (! empty($validated['entity_type'])) {
            $query->where('entity_type', $validated['entity_type']);
        }
        if (! empty($validated['entity_id'])) {
            $query->where('entity_id', (int) $validated['entity_id']);
        }
        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (! empty($validated['actor_user_id'])) {
            $query->where('actor_user_id', (int) $validated['actor_user_id']);
        }
        if (! empty($validated['date_from'])) {
            $query->whereDate('created_at', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->whereDate('created_at', '<=', $validated['date_to']);
        }

        $events = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        // Distinct action values that exist in the table so the filter is a
        // realistic dropdown rather than free text. Cap at 200 in case the
        // namespace explodes — beyond that we'd want a full search anyway.
        $actions = AuditEvent::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->limit(200)
            ->pluck('action')
            ->all();

        $hasFilters = (bool) array_filter([
            $validated['entity_type'] ?? null,
            $validated['entity_id'] ?? null,
            $validated['action'] ?? null,
            $validated['actor_user_id'] ?? null,
            $validated['date_from'] ?? null,
            $validated['date_to'] ?? null,
        ]);

        return view('admin.operations.audit_events.index', [
            'events' => $events,
            'entityTypes' => self::ENTITY_TYPES,
            'actions' => $actions,
            'hasFilters' => $hasFilters,
        ]);
    }
}
