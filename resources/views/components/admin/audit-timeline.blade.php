@props([
    /**
     * Entity-type slug (e.g. 'subscription', 'delivery', 'payment', 'order',
     * 'box', 'box_item', 'user') as written by AuditLogger.
     */
    'entityType',
    /** Entity ID. */
    'entityId',
    /** Maximum events to render inline before linking to the full log. */
    'limit' => 10,
])

@php
    $events = \App\Models\AuditEvent::query()
        ->forEntity($entityType, (int) $entityId)
        ->with('actor:id,email')
        ->orderByDesc('created_at')
        ->orderByDesc('id')
        ->limit($limit + 1)
        ->get();
    $hasMore = $events->count() > $limit;
    $events = $events->take($limit);
@endphp

{{-- Compact embeddable history panel. Renders the most recent N audit
     events for a single entity; links to the full log if the slice is
     truncated. Use on admin detail pages to give support a "what
     happened to this thing" surface without leaving the page. --}}
<section class="rounded-lg border border-gray-100 bg-white shadow-sm" aria-labelledby="audit-timeline-{{ $entityType }}-{{ $entityId }}">
    <div class="flex items-center justify-between border-b border-gray-100 px-4 py-3">
        <h3 id="audit-timeline-{{ $entityType }}-{{ $entityId }}" class="text-sm font-semibold text-gray-900">
            {{ __('admin.audit.timeline_title') }}
        </h3>
        <a
            href="{{ route('app.admin.audit-events.index', ['entity_type' => $entityType, 'entity_id' => $entityId]) }}"
            class="text-xs font-medium text-emerald-700 hover:underline"
        >
            {{ $hasMore ? __('admin.audit.timeline_link_more', ['count' => $hasMore ? '+' : '']) : __('admin.audit.timeline_link_all') }}
        </a>
    </div>

    @if ($events->isEmpty())
        <p class="px-4 py-6 text-sm text-gray-500">{{ __('admin.audit.no_events') }}</p>
    @else
        <ol class="divide-y divide-gray-100">
            @foreach ($events as $event)
                <li class="px-4 py-3 text-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-gray-800">
                                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $event->action }}</code>
                                @if ($event->from_state || $event->to_state)
                                    <span class="ml-2 text-xs text-gray-500">
                                        {{ $event->from_state ?? '—' }}
                                        <span aria-hidden="true">→</span>
                                        <span class="font-medium text-gray-800">{{ $event->to_state ?? '—' }}</span>
                                    </span>
                                @endif
                            </p>
                            @if ($event->reason)
                                <p class="mt-1 text-xs text-gray-600">{{ $event->reason }}</p>
                            @endif
                        </div>
                        <div class="shrink-0 text-right text-xs text-gray-500">
                            <p class="tabular-nums">{{ $event->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</p>
                            <p class="text-gray-400">
                                {{ $event->actor?->email ?? __('admin.audit.actor_system') }}
                            </p>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>
