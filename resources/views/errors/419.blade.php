{{-- Uses the shop layout (not layouts.marketing) so the error page carries
     the shop header — no sign-in / sign-up / box-area nav — keeping the
     parked account surface out of reach from an error page. --}}
<x-shop-layout :title="__('Session expired')">
    <div class="flex min-h-[60vh] items-center justify-center px-4 py-16 sm:py-24">
        <div class="max-w-md text-center">
            <p class="eyebrow text-chapter-produce">{{ __('Error 419') }}</p>
            <h1 class="mt-4 font-display text-5xl font-semibold text-brand-ink sm:text-6xl">{{ __('Session expired') }}</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-ink/75">{{ __('Your session timed out before the form could be submitted. Please sign in again and retry.') }}</p>
            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <x-solid-link :href="route('auth.login')">
                    {{ __('Log in') }}
                </x-solid-link>
                <a href="javascript:history.back()" class="text-sm font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline">
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </div>
</x-shop-layout>
