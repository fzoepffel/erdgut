<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.audit.title') }}
        </h2>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-gray-600">
                {{ __('admin.audit.intro') }}
            </p>

            <div class="rounded-lg border border-gray-100 bg-white shadow-sm sm:rounded-lg">
                <div class="space-y-4 p-4 text-gray-900 sm:p-6">
                    <form method="get" action="{{ route('app.admin.audit-events.index') }}" class="flex flex-col flex-wrap gap-3 md:flex-row md:items-end">
                        <div class="w-full md:w-auto">
                            <label for="audit-entity-type" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.audit.filter_entity_type') }}</label>
                            <select id="audit-entity-type" name="entity_type" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 md:w-44">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                @foreach ($entityTypes as $type)
                                    <option value="{{ $type }}" @selected(request('entity_type') === $type)>{{ __('admin.audit.entity_type.'.$type) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full md:w-auto">
                            <label for="audit-entity-id" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.audit.filter_entity_id') }}</label>
                            <input id="audit-entity-id" type="number" min="1" name="entity_id" value="{{ request('entity_id') }}" placeholder="ID" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 md:w-32" />
                        </div>
                        <div class="w-full md:w-auto">
                            <label for="audit-action" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.audit.filter_action') }}</label>
                            <select id="audit-action" name="action" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 md:w-56">
                                <option value="">{{ __('admin.operations.filter_all') }}</option>
                                @foreach ($actions as $action)
                                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="w-full md:w-auto">
                            <label for="audit-actor" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.audit.filter_actor_id') }}</label>
                            <input id="audit-actor" type="number" min="1" name="actor_user_id" value="{{ request('actor_user_id') }}" placeholder="user ID" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 md:w-32" />
                        </div>
                        <div class="w-full md:w-auto">
                            <label for="audit-from" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.date_from') }}</label>
                            <input id="audit-from" type="date" name="date_from" value="{{ request('date_from') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </div>
                        <div class="w-full md:w-auto">
                            <label for="audit-to" class="mb-1 block text-xs font-medium text-gray-600">{{ __('admin.operations.date_to') }}</label>
                            <input id="audit-to" type="date" name="date_to" value="{{ request('date_to') }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600" />
                        </div>
                        <div class="flex gap-2">
                            <x-primary-button size="sm">
                                {{ __('admin.catalog.apply_filters') }}
                            </x-primary-button>
                            @if ($hasFilters)
                                <a href="{{ route('app.admin.audit-events.index') }}" class="btn-ghost-sm">
                                    {{ __('admin.catalog.reset_filters') }}
                                </a>
                            @endif
                        </div>
                    </form>

                    @if ($events->isEmpty())
                        <p class="text-sm text-gray-600">{{ __('admin.audit.no_events') }}</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead>
                                    <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                                        <th class="whitespace-nowrap py-2 pe-4">{{ __('admin.audit.col_when') }}</th>
                                        <th class="whitespace-nowrap py-2 pe-4">{{ __('admin.audit.col_actor') }}</th>
                                        <th class="whitespace-nowrap py-2 pe-4">{{ __('admin.audit.col_entity') }}</th>
                                        <th class="whitespace-nowrap py-2 pe-4">{{ __('admin.audit.col_action') }}</th>
                                        <th class="whitespace-nowrap py-2 pe-4">{{ __('admin.audit.col_transition') }}</th>
                                        <th class="py-2 pe-4">{{ __('admin.audit.col_reason') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($events as $event)
                                        <tr class="align-top">
                                            <td class="whitespace-nowrap py-3 pe-4 tabular-nums text-gray-600">
                                                {{ $event->created_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="whitespace-nowrap py-3 pe-4">
                                                @if ($event->actor)
                                                    <span class="text-gray-900">{{ $event->actor->email }}</span>
                                                    <span class="block text-xs text-gray-500">#{{ $event->actor->id }}</span>
                                                @else
                                                    <span class="text-gray-400">{{ __('admin.audit.actor_system') }}</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap py-3 pe-4">
                                                <span class="text-gray-700">{{ __('admin.audit.entity_type.'.$event->entity_type) }}</span>
                                                <span class="block text-xs text-gray-500">#{{ $event->entity_id }}</span>
                                            </td>
                                            <td class="whitespace-nowrap py-3 pe-4">
                                                <code class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-800">{{ $event->action }}</code>
                                            </td>
                                            <td class="whitespace-nowrap py-3 pe-4 text-xs text-gray-600">
                                                @if ($event->from_state || $event->to_state)
                                                    <span class="text-gray-500">{{ $event->from_state ?? '—' }}</span>
                                                    <span class="mx-1 text-gray-400" aria-hidden="true">→</span>
                                                    <span class="font-medium text-gray-800">{{ $event->to_state ?? '—' }}</span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="py-3 pe-4 text-xs text-gray-600">
                                                @if ($event->reason)
                                                    <p class="max-w-md whitespace-normal break-words">{{ $event->reason }}</p>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                                @if (! empty($event->metadata))
                                                    <details class="mt-1">
                                                        <summary class="cursor-pointer text-[11px] text-gray-500 hover:text-gray-700">
                                                            {{ __('admin.audit.metadata') }}
                                                        </summary>
                                                        <pre class="mt-1 max-w-md whitespace-pre-wrap break-words rounded bg-gray-50 p-2 text-[11px] text-gray-700">{{ json_encode($event->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                    </details>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">{{ $events->links() }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
