@php
    /** @var list<\App\Services\Admin\AdminOperationalAlert> $alerts */
@endphp

<section
    class="rounded-lg border border-amber-200 bg-amber-50/80 p-4 shadow-sm"
    aria-labelledby="admin-operational-alerts-heading"
    data-testid="admin-operational-alerts"
>
    <h3 id="admin-operational-alerts-heading" class="text-sm font-semibold text-amber-950">
        {{ __('admin.alerts.title') }}
    </h3>

    @if (count($alerts) === 0)
        <p class="mt-2 text-sm text-amber-900/90" data-testid="admin-operational-alerts-clear">
            {{ __('admin.alerts.all_clear') }}
        </p>
    @else
        <ul class="mt-3 space-y-3" role="alert" aria-live="polite">
            @foreach ($alerts as $alert)
                <li class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                    <div class="flex gap-2 text-sm text-amber-950">
                        <span class="mt-0.5 shrink-0 text-red-600" aria-hidden="true">
                            <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                <path
                                    fill-rule="evenodd"
                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                        </span>
                        <span>{{ __($alert->messageKey, $alert->messageParams) }}</span>
                    </div>
                    <a
                        href="{{ $alert->href }}"
                        class="shrink-0 text-sm font-semibold text-emerald-800 underline decoration-emerald-700/50 hover:text-emerald-950"
                    >
                        {{ __($alert->linkTextKey) }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</section>
