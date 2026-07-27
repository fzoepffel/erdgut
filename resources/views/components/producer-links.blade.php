@props([
    // Iterable of Producer models (or nulls). Strings are ignored for linking.
    'producers' => [],
    // Route names — shop surfaces must stay on public.shop.producers.*
    'showRoute' => \App\Support\ProducerLabels::SHOW_ROUTE_SHOP,
    'indexRoute' => \App\Support\ProducerLabels::INDEX_ROUTE_SHOP,
    // Fallback plain text when nothing is linkable (e.g. unknown farm).
    'fallback' => null,
])

@php
    $links = \App\Support\ProducerLabels::linkData($producers, $showRoute, $indexRoute);
@endphp

@if ($links['collapsed'])
    <a {{ $attributes->merge(['href' => $links['index_url'], 'class' => 'underline-offset-4 hover:underline']) }} data-testid="producer-links-collapsed">{{ __('shop.producers_multiple') }}</a>
@elseif ($links['items'] !== [])
    <span {{ $attributes->merge(['class' => '']) }} data-testid="producer-links">
        @foreach ($links['items'] as $producerLink)
            @if (! $loop->first)<span aria-hidden="true"> · </span>@endif<a href="{{ $producerLink['url'] }}" class="underline-offset-4 hover:underline" data-testid="producer-link-{{ $producerLink['id'] }}">{{ $producerLink['name'] }}</a>
        @endforeach
    </span>
@elseif (filled($fallback))
    <span {{ $attributes }}>{{ $fallback }}</span>
@endif
