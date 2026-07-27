@props([
    // Iterable of Producer models (or nulls). Strings are ignored for linking.
    'producers' => [],
    // Route names — shop surfaces must stay on public.shop.producers.*
    'showRoute' => \App\Support\ProducerLabels::SHOW_ROUTE_SHOP,
    'indexRoute' => \App\Support\ProducerLabels::INDEX_ROUTE_SHOP,
    // Fallback plain text when nothing is linkable (e.g. unknown farm).
    'fallback' => null,
    // Optional trailing plain text (e.g. uppercased region) joined with a middot.
    'suffix' => null,
])

@php
    $links = \App\Support\ProducerLabels::linkData($producers, $showRoute, $indexRoute);
    $suffixText = filled($suffix) ? (string) $suffix : '';
    $suffixHtml = $suffixText !== '' ? '<span aria-hidden="true"> · </span>'.e($suffixText) : '';

    $inner = '';
    if ($links['collapsed']) {
        $inner = e(__('shop.producers_multiple'));
    } elseif ($links['items'] !== []) {
        foreach ($links['items'] as $i => $producerLink) {
            if ($i > 0) {
                $inner .= '<span aria-hidden="true"> · </span>';
            }
            $inner .= '<a href="'.e($producerLink['url']).'" class="underline-offset-4 hover:underline" data-testid="producer-link-'.e((string) $producerLink['id']).'">'.e($producerLink['name']).'</a>';
        }
    } elseif (filled($fallback)) {
        $inner = e((string) $fallback);
    }
@endphp

@if ($links['collapsed'])
    <a {{ $attributes->merge(['href' => $links['index_url'], 'class' => 'underline-offset-4 hover:underline']) }} data-testid="producer-links-collapsed">{{ __('shop.producers_multiple') }}</a>{!! $suffixHtml !!}
@elseif ($inner !== '')
    <span {{ $attributes->merge(['class' => '']) }} data-testid="producer-links">{!! $inner.$suffixHtml !!}</span>
@elseif ($suffixHtml !== '')
    <span {{ $attributes }}>{!! $suffixHtml !!}</span>
@endif
