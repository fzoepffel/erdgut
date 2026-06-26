{{-- Uses the shop layout (not layouts.marketing) so the error page carries
     the shop header — no sign-in / sign-up / box-area nav — keeping the
     parked account surface out of reach from an error page. --}}
<x-shop-layout :title="__('Maintenance')">
    <div class="flex min-h-[60vh] items-center justify-center px-4 py-16 sm:py-24">
        <div class="max-w-md text-center">
            <p class="eyebrow text-chapter-fish">{{ __('Error 503') }}</p>
            <h1 class="mt-4 font-display text-5xl font-semibold text-brand-ink sm:text-6xl">{{ __('Maintenance') }}</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-ink/75">{{ __('We will be back shortly. Please try again in a few minutes.') }}</p>
        </div>
    </div>
</x-shop-layout>
