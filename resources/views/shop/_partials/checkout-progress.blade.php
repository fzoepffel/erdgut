{{-- Three-step checkout progress indicator. Renders the same on /shop,
     /shop/checkout/details, /shop/checkout/review so the customer
     always knows where they are. Pass `:active="1|2|3"` from the
     calling view; earlier steps render as completed (linkable),
     later steps render as upcoming (muted, not linkable).

     Each step is a small uppercase eyebrow + numeric badge. No icons —
     keeps the editorial brand voice. --}}
@props([
    'active' => 1,
])
@php
    $steps = [
        ['n' => 1, 'label' => __('shop.checkout.step_basket'), 'route' => 'public.shop.index'],
        ['n' => 2, 'label' => __('shop.checkout.step_details'), 'route' => 'public.shop.checkout.details'],
        ['n' => 3, 'label' => __('shop.checkout.step_review'), 'route' => 'public.shop.checkout.review'],
    ];
@endphp
<nav
    aria-label="{{ __('shop.checkout.progress_label') }}"
    class="border-b border-shop-border bg-shop-paper"
    data-testid="shop-checkout-progress"
>
    <ol class="mx-auto flex max-w-[1100px] flex-wrap items-center gap-y-2 px-6 py-4 text-[0.65rem] uppercase tracking-widest lg:px-12">
        @foreach ($steps as $step)
            @php
                $isActive = $step['n'] === $active;
                $isDone = $step['n'] < $active;
                $isLinkable = $isDone;
            @endphp
            <li class="flex items-center gap-3 pr-5">
                @if ($isLinkable)
                    <a href="{{ route($step['route']) }}"
                       class="flex items-center gap-2 text-shop-primary underline-offset-4 hover:underline"
                       data-testid="shop-checkout-step-{{ $step['n'] }}-link">
                        <span class="inline-flex h-6 w-6 items-center justify-center border border-shop-primary text-[0.7rem]">{{ $step['n'] }}</span>
                        <span>{{ $step['label'] }}</span>
                    </a>
                @else
                    <span class="flex items-center gap-2 {{ $isActive ? 'text-shop-foreground' : 'text-shop-muted' }}"
                          @if ($isActive) aria-current="step" @endif
                          data-testid="shop-checkout-step-{{ $step['n'] }}">
                        <span class="inline-flex h-6 w-6 items-center justify-center border {{ $isActive ? 'border-shop-primary bg-shop-primary text-shop-primary-foreground' : 'border-shop-border' }} text-[0.7rem]">{{ $step['n'] }}</span>
                        <span>{{ $step['label'] }}</span>
                    </span>
                @endif
            </li>
            @if (! $loop->last)
                <li aria-hidden="true" class="hidden text-shop-border sm:inline">·</li>
            @endif
        @endforeach
    </ol>
</nav>
