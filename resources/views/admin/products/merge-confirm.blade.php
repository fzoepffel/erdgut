<x-admin-layout>
    <x-slot name="breadcrumbs">
        <x-admin.breadcrumbs :items="[
            ['label' => __('admin.shell.overview'), 'href' => route('app.admin.dashboard')],
            ['label' => __('Products'), 'href' => route('app.admin.products.index')],
            ['label' => $loser->name, 'href' => route('app.admin.products.edit', $loser)],
            ['label' => __('admin.products.merge.confirm_title')],
        ]" />
    </x-slot>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.products.merge.confirm_title') }}
        </h1>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900" role="alert">
                {{ __('admin.products.merge.confirm_intro', ['loser' => $loser->name, 'winner' => $winner->name]) }}
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6 text-gray-900">
                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.products.merge.moves_title') }}</h2>
                        <dl class="mt-3 divide-y divide-gray-100 text-sm" data-testid="merge-moves">
                            @foreach ([
                                __('admin.products.merge.moves_inflows') => $counts['inflows'],
                                __('admin.products.merge.moves_schedules') => $counts['schedules'],
                                __('admin.products.merge.moves_shop_lines') => $counts['shop_lines'],
                                __('admin.products.merge.moves_restaurant_lines') => $counts['restaurant_lines'],
                                __('admin.products.merge.moves_standing_lines') => $counts['standing_lines'],
                                __('admin.products.merge.moves_box_items') => $counts['box_items'],
                                __('admin.products.merge.moves_engagement') => $counts['engagement_days'],
                                __('admin.products.merge.moves_pairings') => $counts['pairings'],
                            ] as $label => $value)
                                <div class="flex items-center justify-between py-2">
                                    <dt class="text-gray-600">{{ $label }}</dt>
                                    <dd class="font-medium tabular-nums">{{ $value }}</dd>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between gap-4 py-2">
                                <dt class="text-gray-600">{{ __('admin.products.merge.moves_variants_moved') }}</dt>
                                <dd class="text-right font-medium">{{ $counts['variants_moved'] !== [] ? implode(', ', $counts['variants_moved']) : '0' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2">
                                <dt class="text-gray-600">{{ __('admin.products.merge.moves_variants_remapped') }}</dt>
                                <dd class="text-right font-medium">{{ $counts['variants_remapped'] !== [] ? implode(', ', $counts['variants_remapped']) : '0' }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-4 py-2">
                                <dt class="text-gray-600">{{ __('admin.products.merge.moves_producers') }}</dt>
                                <dd class="text-right font-medium">{{ $counts['producers_appended'] !== [] ? implode(' · ', $counts['producers_appended']) : '0' }}</dd>
                            </div>
                        </dl>
                    </div>

                    <div>
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">{{ __('admin.products.merge.drops_title') }}</h2>
                        <ul class="mt-3 list-inside list-disc space-y-1 text-sm text-gray-600" data-testid="merge-drops">
                            <li>{{ __('admin.products.merge.drops_translations') }}</li>
                            <li>{{ __('admin.products.merge.drops_images') }}: {{ $counts['dropped_images'] }}</li>
                            <li>{{ __('admin.products.merge.drops_price_rules') }}: {{ $counts['dropped_price_rules'] }}</li>
                        </ul>
                    </div>

                    <form method="post" action="{{ route('app.admin.products.merge.execute', $loser) }}" class="flex items-center gap-4 border-t border-gray-100 pt-6">
                        @csrf
                        <input type="hidden" name="winner_id" value="{{ $winner->id }}" />
                        <x-danger-button type="submit" data-testid="merge-execute-button">
                            {{ __('admin.products.merge.confirm_button') }}
                        </x-danger-button>
                        <a href="{{ route('app.admin.products.edit', $loser) }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">
                            {{ __('admin.products.merge.cancel') }}
                        </a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
