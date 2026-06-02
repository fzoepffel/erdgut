{{-- Shop availability call-to-action for a single product.

     Rendered on the shop's product- and producer-detail pages so a guest
     can jump straight back into the storefront. Hidden entirely on the
     marketing-shell catalogue (the partial is only included when $cta is
     passed in).

     Required param:
       $cta — array{state: 'this_week'|'future'|'none', date: ?Carbon, url: string}

     Optional param:
       $block — bool (default false). When true the button stretches full
                width (producer product cards); otherwise it sits inline. --}}
@php
    $block = $block ?? false;
    $widthClass = $block ? 'w-full justify-center' : 'self-start';
    $dateLabel = $cta['date']
        ? $cta['date']->locale(app()->getLocale())->isoFormat('dd, D. MMMM')
        : null;
@endphp

@if ($cta['state'] === 'this_week')
    <a
        href="{{ $cta['url'] }}"
        class="inline-flex items-center gap-2 border border-brand-moss bg-brand-moss px-6 py-3 text-xs uppercase tracking-eyebrow text-brand-cream transition hover:bg-brand-ink hover:border-brand-ink {{ $widthClass }}"
        data-testid="shop-cta-available"
    >
        {{ __('shop.cta_order_in_shop') }} <span aria-hidden="true">&rarr;</span>
    </a>
@elseif ($cta['state'] === 'future')
    <a
        href="{{ $cta['url'] }}"
        class="inline-flex items-center gap-2 border border-brand-moss px-6 py-3 text-xs uppercase tracking-eyebrow text-brand-moss transition hover:bg-brand-moss hover:text-brand-cream {{ $widthClass }}"
        data-testid="shop-cta-future"
    >
        {{ __('shop.cta_available_again', ['date' => $dateLabel]) }} <span aria-hidden="true">&rarr;</span>
    </a>
@else
    <span
        class="inline-flex items-center gap-2 border border-brand-ink/20 px-6 py-3 text-xs uppercase tracking-eyebrow text-brand-ink/45 {{ $widthClass }}"
        data-testid="shop-cta-unavailable"
    >
        {{ __('shop.cta_unavailable') }}
    </span>
@endif
