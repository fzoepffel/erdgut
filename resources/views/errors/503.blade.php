@extends('layouts.marketing')

@section('content')
<div class="flex min-h-[60vh] items-center justify-center px-4 py-16 sm:py-24">
    <div class="max-w-md text-center">
        <p class="eyebrow text-category-fish">{{ __('Error 503') }}</p>
        <h1 class="mt-4 font-display text-5xl font-semibold text-brand-ink sm:text-6xl">{{ __('Maintenance') }}</h1>
        <p class="mt-4 text-base leading-relaxed text-brand-ink/75">{{ __('We will be back shortly. Please try again in a few minutes.') }}</p>
    </div>
</div>
@endsection
