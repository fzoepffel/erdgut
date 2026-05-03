@props([
    // Each item: ['label' => 'Box-Setup', 'href' => route(...)] — the href
    // is optional; the last item is always rendered as the current page
    // (no link, aria-current="page") regardless.
    'items' => [],
])

@if (! empty($items))
    <nav aria-label="{{ __('admin.breadcrumbs.aria_label') }}" class="text-xs">
        <ol class="flex flex-wrap items-center gap-1.5 text-gray-500">
            @foreach ($items as $item)
                <li class="flex items-center gap-1.5">
                    @if (isset($item['href']) && ! $loop->last)
                        <a
                            href="{{ $item['href'] }}"
                            class="rounded transition hover:text-gray-700 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 focus-visible:ring-offset-2"
                        >{{ $item['label'] }}</a>
                    @else
                        <span
                            class="font-medium text-gray-700"
                            @if ($loop->last) aria-current="page" @endif
                        >{{ $item['label'] }}</span>
                    @endif
                    @unless ($loop->last)
                        <span aria-hidden="true" class="text-gray-300">›</span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>
@endif
