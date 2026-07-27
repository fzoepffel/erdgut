<x-admin-layout>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('admin.products.csv.preview_title') }}
        </h1>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- Summary counts --}}
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                <div class="rounded-md bg-emerald-50 p-4">
                    <div class="text-2xl font-semibold text-emerald-800">{{ $result['summary']['to_create'] }}</div>
                    <div class="text-sm text-emerald-700">{{ __('admin.products.csv.to_create') }}</div>
                </div>
                <div class="rounded-md bg-blue-50 p-4">
                    <div class="text-2xl font-semibold text-blue-800">{{ $result['summary']['to_update'] }}</div>
                    <div class="text-sm text-blue-700">{{ __('admin.products.csv.to_update') }}</div>
                </div>
                <div class="rounded-md bg-gray-50 p-4">
                    <div class="text-2xl font-semibold text-gray-800">{{ count($result['summary']['new_groups']) }}</div>
                    <div class="text-sm text-gray-600">{{ __('admin.products.csv.new_groups') }}</div>
                </div>
                <div class="rounded-md bg-amber-50 p-4">
                    <div class="text-2xl font-semibold text-amber-800">{{ $result['summary']['weekly_changes'] }}</div>
                    <div class="text-sm text-amber-700">{{ __('admin.products.csv.weekly_changes') }}</div>
                </div>
            </div>

            {{-- Errors (skipped rows) --}}
            @if (! empty($result['errors']))
                <div class="rounded-md border border-red-200 bg-red-50 p-4">
                    <h2 class="mb-2 text-sm font-semibold text-red-800">{{ __('admin.products.csv.skipped_rows') }}</h2>
                    <ul class="space-y-1 text-sm text-red-700">
                        @foreach ($result['errors'] as $error)
                            <li>{{ __('admin.products.csv.row') }} {{ $error['row'] }}: {{ $error['message'] }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Product change list --}}
            @if (! empty($result['products']))
                <div class="overflow-hidden rounded-md border border-gray-200 bg-white">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.row') }}</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.action') }}</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.product') }}</th>
                                <th class="px-4 py-2 text-left font-semibold text-gray-600">{{ __('admin.products.csv.notes') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($result['products'] as $product)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $product['row'] }}</td>
                                    <td class="px-4 py-2">
                                        @if ($product['action'] === 'create')
                                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ __('admin.products.csv.create') }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">{{ __('admin.products.csv.update') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 font-medium text-gray-800">{{ $product['name'] }}</td>
                                    <td class="px-4 py-2 text-gray-500">
                                        @if (! empty($product['weekly']))
                                            <span class="mr-2">{{ trans_choice('admin.products.csv.weeks_set', count($product['weekly']), ['count' => count($product['weekly'])]) }}</span>
                                        @endif
                                        @foreach ($product['warnings'] as $warning)
                                            <span class="block text-xs text-amber-700">{{ $warning }}</span>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @php
                $newGroups = $result['summary']['new_groups'] ?? [];
                $newSubcategories = $result['summary']['new_subcategories'] ?? [];
                // Repopulate the dialog after a failed confirm: index old input by label.
                $oldGroups = collect($groupInput ?? [])->keyBy(fn ($e) => mb_strtolower(trim((string) ($e['name'] ?? ''))));
                $oldSubcategories = collect($subInput ?? [])->keyBy(fn ($e) => mb_strtolower(trim((string) ($e['name'] ?? ''))));
            @endphp

            {{-- Confirm (with the missing-info dialog for new groups/subcategories) --}}
            @if (! empty($result['products']))
                <form method="POST" action="{{ route('app.admin.products.import.confirm') }}" class="space-y-6">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <input type="hidden" name="ext" value="{{ $ext }}">

                    @if (! empty($newGroups) || ! empty($newSubcategories))
                        <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                            <h2 class="text-sm font-semibold text-amber-900">{{ __('admin.products.csv.missing_info_title') }}</h2>
                            <p class="mt-1 text-sm text-amber-800">{{ __('admin.products.csv.missing_info_intro') }}</p>

                            @if (! empty($translationError))
                                <p class="mt-2 rounded bg-red-100 px-3 py-2 text-sm font-medium text-red-800">{{ $translationError }}</p>
                            @endif

                            <div class="mt-4 space-y-4">
                                @foreach ($newGroups as $i => $label)
                                    @php $old = $oldGroups->get(mb_strtolower(trim((string) $label))); @endphp
                                    <div class="rounded border border-amber-200 bg-white p-3">
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('admin.products.csv.new_group_word') }}</div>
                                        <input type="hidden" name="group_translations[{{ $i }}][name]" value="{{ $label }}">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">DE</label>
                                                <input type="text" required maxlength="120" name="group_translations[{{ $i }}][de]"
                                                       value="{{ $old['de'] ?? $label }}"
                                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">EN</label>
                                                <input type="text" required maxlength="120" name="group_translations[{{ $i }}][en]"
                                                       value="{{ $old['en'] ?? '' }}"
                                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">FR</label>
                                                <input type="text" required maxlength="120" name="group_translations[{{ $i }}][fr]"
                                                       value="{{ $old['fr'] ?? '' }}"
                                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @foreach ($newSubcategories as $i => $label)
                                    @php $old = $oldSubcategories->get(mb_strtolower(trim((string) $label))); @endphp
                                    <div class="rounded border border-amber-200 bg-white p-3">
                                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-amber-700">{{ __('admin.products.csv.new_subcategory_word') }}</div>
                                        <input type="hidden" name="subcategory_translations[{{ $i }}][name]" value="{{ $label }}">
                                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">DE</label>
                                                <input type="text" required maxlength="120" name="subcategory_translations[{{ $i }}][de]"
                                                       value="{{ $old['de'] ?? $label }}"
                                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">EN</label>
                                                <input type="text" required maxlength="120" name="subcategory_translations[{{ $i }}][en]"
                                                       value="{{ $old['en'] ?? '' }}"
                                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-gray-600">FR</label>
                                                <input type="text" required maxlength="120" name="subcategory_translations[{{ $i }}][fr]"
                                                       value="{{ $old['fr'] ?? '' }}"
                                                       class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('app.admin.products.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn-solid-sm">
                            {{ __('admin.products.csv.confirm') }}
                        </button>
                    </div>
                </form>
            @else
                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('app.admin.products.index') }}" class="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50">
                        {{ __('Cancel') }}
                    </a>
                </div>
            @endif
        </div>
    </div>
</x-admin-layout>
