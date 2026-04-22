@extends('layouts.marketing')

@section('content')
<div class="flex min-h-[60vh] items-center justify-center px-4 py-16 sm:py-24">
    <div class="max-w-md text-center">
        <p class="eyebrow text-chapter-meat">{{ __('Error 500') }}</p>
        <h1 class="mt-4 font-display text-5xl font-semibold text-brand-ink sm:text-6xl">{{ __('Something went wrong') }}</h1>
        <p class="mt-4 text-base leading-relaxed text-brand-ink/75">{{ __('Please try again or contact us if the problem persists.') }}</p>
        <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
            <x-ghost-link :href="url()->previous()">
                {{ __('Try again') }}
            </x-ghost-link>
            <x-solid-link :href="route('public.contact')">
                {{ __('Contact') }}
            </x-solid-link>
        </div>
    </div>
</div>
@endsection
