{{-- Uses the shop layout (not layouts.marketing) so the error page carries
     the shop header — no sign-in / sign-up / box-area nav — keeping the
     parked account surface out of reach from an error page. --}}
<x-shop-layout :title="__('Access denied')">
    <div class="flex min-h-[60vh] items-center justify-center px-4 py-16 sm:py-24">
        <div class="max-w-md text-center">
            <p class="eyebrow text-chapter-meat">{{ __('Error 403') }}</p>
            <h1 class="mt-4 font-display text-5xl font-semibold text-brand-ink sm:text-6xl">{{ __('Access denied') }}</h1>
            <p class="mt-4 text-base leading-relaxed text-brand-ink/75">{{ __('You do not have permission to access this page.') }}</p>
            <div class="mt-8 flex justify-center">
                @auth
                    <x-solid-link :href="route('app.dashboard')">
                        {{ __('Go to dashboard') }}
                    </x-solid-link>
                @else
                    <x-solid-link :href="route('public.home')">
                        {{ __('Back to home') }}
                    </x-solid-link>
                @endauth
            </div>
        </div>
    </div>
</x-shop-layout>
