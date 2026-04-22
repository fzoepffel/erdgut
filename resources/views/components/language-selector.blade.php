@props([
    'variant' => 'default',
    'align' => 'right',
])

@php
    $supported = (array) config('app.supported_locales', ['en']);
    $current = app()->getLocale();

    $labels = [
        'de' => 'Deutsch',
        'en' => 'English',
        'fr' => 'Français',
    ];

    $isCompact = $variant === 'compact';
@endphp

@if (count($supported) > 1)
    <div
        data-testid="language-selector"
        data-current-locale="{{ $current }}"
        class="inline-flex"
    >
        <x-dropdown align="{{ $align }}" width="40">
            <x-slot name="trigger">
                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-full px-2.5 py-1.5 text-xs font-semibold uppercase tracking-wide text-brand-ink/80 transition hover:text-brand-ink focus:outline-none"
                    aria-label="{{ __('Language') }}"
                >
                    <span>{{ strtoupper($current) }}</span>
                    @unless ($isCompact)
                        <svg class="h-3 w-3 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    @endunless
                </button>
            </x-slot>

            <x-slot name="content">
                @foreach ($supported as $code)
                    @php
                        $isActive = $code === $current;
                        $label = $labels[$code] ?? strtoupper($code);
                    @endphp
                    <a
                        href="{{ route('public.locale.switch', $code) }}"
                        class="flex items-center justify-between px-4 py-2 text-sm transition hover:bg-brand-ink/5 {{ $isActive ? 'font-semibold text-brand-ink' : 'text-brand-ink/80' }}"
                        data-locale="{{ $code }}"
                        @if ($isActive) aria-current="true" @endif
                    >
                        <span>{{ $label }}</span>
                        <span class="text-xs uppercase tracking-wide text-brand-ink/40">{{ strtoupper($code) }}</span>
                    </a>
                @endforeach
            </x-slot>
        </x-dropdown>
    </div>
@endif
