@extends('layouts.marketing')

@section('content')
    <article class="bg-brand-cream pb-16 pt-12 sm:pt-16" aria-labelledby="recipe-title">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <p class="eyebrow text-chapter-produce">{{ __('customer.dashboard.recipe_feature.eyebrow') }}</p>

            <h1 id="recipe-title" class="mt-4 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl lg:text-6xl">
                {{ $recipe['title'] }}
            </h1>

            <div class="mt-10 grid gap-10 lg:grid-cols-12 lg:gap-12">
                <div class="lg:col-span-7">
                    <div class="aspect-[4/3] w-full overflow-hidden bg-brand-paper">
                        <img
                            src="{{ $recipe['photo'] }}"
                            alt=""
                            role="presentation"
                            class="h-full w-full object-cover"
                            loading="lazy"
                            decoding="async"
                        />
                    </div>
                </div>

                <div class="lg:col-span-5">
                    <p class="font-editorial text-lg leading-relaxed text-brand-ink/85 sm:text-xl">
                        {{ $recipe['description'] }}
                    </p>

                    <dl class="mt-10 grid grid-cols-2 gap-x-8 gap-y-6 border-t border-brand-ink/15 pt-8">
                        <div>
                            <dt class="eyebrow text-brand-ink/60">{{ __('customer.dashboard.recipe_feature.metric_time') }}</dt>
                            <dd class="mt-2 font-display text-2xl text-brand-ink">
                                {{ __('customer.dashboard.recipe_feature.time_value', ['n' => $recipe['time_minutes']]) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="eyebrow text-brand-ink/60">{{ __('customer.dashboard.recipe_feature.metric_serves') }}</dt>
                            <dd class="mt-2 font-display text-2xl text-brand-ink">{{ $recipe['serves'] }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </article>
@endsection
