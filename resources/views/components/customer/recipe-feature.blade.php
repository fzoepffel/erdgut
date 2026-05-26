@props([
    // Recipe payload — same shape as EditorialContent::recipe().
    // Required keys: slug, title, description, time_minutes, serves, photo.
    // ingredient_slugs is consumed at the call site to derive the from-box
    // overlap metric; the component itself receives the already-computed
    // counts so it stays presentational.
    'recipe' => [],

    // Optional from-box overlap. When both keys are non-null and totalCount
    // is non-zero, the third metric ("Aus der Box X von Y") renders;
    // otherwise that metric is omitted (e.g. when no box is composed yet).
    'matchedCount' => null,
    'totalCount' => null,
])

@php
    $slug = $recipe['slug'] ?? null;
    $title = $recipe['title'] ?? '';
    $description = $recipe['description'] ?? '';
    $time = (int) ($recipe['time_minutes'] ?? 0);
    $serves = (int) ($recipe['serves'] ?? 0);
    $photo = $recipe['photo'] ?? null;
    $showFromBox = $matchedCount !== null && $totalCount !== null && $totalCount > 0;
@endphp

<section
    {{ $attributes->class(['relative']) }}
    data-testid="customer-recipe-feature"
    aria-labelledby="recipe-feature-title-{{ $slug ?? 'x' }}"
>
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12 lg:gap-12">
        {{-- Text column — lg+ takes leading 5 cols (≈ 40%), photo gets the rest. --}}
        <div class="flex flex-col justify-center lg:order-1 lg:col-span-5">
            <p class="eyebrow text-chapter-produce">{{ __('customer.dashboard.recipe_feature.eyebrow') }}</p>

            <h2 id="recipe-feature-title-{{ $slug ?? 'x' }}" class="mt-5 font-display text-3xl leading-tight text-brand-ink sm:text-4xl lg:text-[2.5rem]">
                {{ $title }}
            </h2>

            @if ($description)
                <p class="mt-5 max-w-md text-base leading-relaxed text-brand-ink/75">
                    {{ $description }}
                </p>
            @endif

            <dl class="mt-7 grid grid-cols-3 gap-x-4 gap-y-2 max-w-md">
                <div>
                    <dt class="eyebrow text-brand-ink/60">{{ __('customer.dashboard.recipe_feature.metric_time') }}</dt>
                    <dd class="mt-2 font-display text-xl text-brand-ink">
                        {{ __('customer.dashboard.recipe_feature.time_value', ['n' => $time]) }}
                    </dd>
                </div>
                <div>
                    <dt class="eyebrow text-brand-ink/60">{{ __('customer.dashboard.recipe_feature.metric_serves') }}</dt>
                    <dd class="mt-2 font-display text-xl text-brand-ink">{{ $serves }}</dd>
                </div>
                @if ($showFromBox)
                    <div>
                        <dt class="eyebrow text-brand-ink/60">{{ __('customer.dashboard.recipe_feature.metric_from_box') }}</dt>
                        <dd class="mt-2 font-display text-xl text-brand-ink">
                            {{ __('customer.dashboard.recipe_feature.from_box_value', ['matched' => $matchedCount, 'total' => $totalCount]) }}
                        </dd>
                    </div>
                @endif
            </dl>

            @if ($slug)
                <a
                    href="{{ route('public.recipes.show', ['slug' => $slug]) }}"
                    class="mt-7 inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-ink underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-chapter-produce"
                    data-testid="customer-recipe-feature-link"
                    data-track-section="recipe_feature.open"
                    data-track-destination="public.recipes.show"
                >
                    {{ __('customer.dashboard.recipe_feature.cta') }}
                    <span aria-hidden="true">&rarr;</span>
                </a>
            @endif
        </div>

        {{-- Photo column — lg+ on the right, stacks above text on mobile. --}}
        <div class="lg:order-2 lg:col-span-7">
            @if ($photo)
                <div class="aspect-[4/3] w-full overflow-hidden bg-brand-paper">
                    <img
                        src="{{ $photo }}"
                        alt=""
                        role="presentation"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                </div>
            @endif
        </div>
    </div>
</section>
