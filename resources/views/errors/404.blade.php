@extends('layouts.marketing')

@section('content')
<div class="flex min-h-[60vh] items-center justify-center px-4 py-16 sm:py-24">
    <div class="max-w-md text-center">
        <p class="eyebrow text-chapter-produce">{{ __('Error 404') }}</p>
        <h1 class="mt-4 font-display text-5xl font-semibold text-brand-ink sm:text-6xl">{{ __('Page not found') }}</h1>
        <p class="mt-4 text-base leading-relaxed text-brand-ink/75">{{ __('The page you are looking for does not exist or has been moved.') }}</p>
        <div class="mt-8 flex justify-center">
            <x-solid-link :href="route('public.home')">
                {{ __('Back to home') }}
            </x-solid-link>
        </div>
    </div>
</div>
@endsection
