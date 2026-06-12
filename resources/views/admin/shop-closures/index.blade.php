<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.shop_closures.title') }}
            </h1>
            <a href="{{ route('app.admin.shop-orders.index') }}" class="text-sm text-gray-500 hover:text-gray-700">
                {{ __('shop.admin.nav_label') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-600">{{ __('admin.shop_closures.intro') }}</p>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.create_heading') }}</h2>
                </div>
                <form method="POST" action="{{ route('app.admin.shop-closures.store') }}" class="space-y-4 p-6">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="starts_on" class="block text-sm font-medium text-gray-700">{{ __('admin.shop_closures.field_starts_on') }}</label>
                            <input type="date" id="starts_on" name="starts_on" value="{{ old('starts_on', $tomorrow) }}" required
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <x-input-error :messages="$errors->get('starts_on')" class="mt-1" />
                        </div>
                        <div>
                            <label for="ends_on" class="block text-sm font-medium text-gray-700">{{ __('admin.shop_closures.field_ends_on') }}</label>
                            <input type="date" id="ends_on" name="ends_on" value="{{ old('ends_on') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                            <p class="mt-1 text-xs text-gray-500">{{ __('admin.shop_closures.field_ends_on_hint') }}</p>
                            <x-input-error :messages="$errors->get('ends_on')" class="mt-1" />
                        </div>
                    </div>
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">{{ __('admin.shop_closures.field_reason') }}</label>
                        <input type="text" id="reason" name="reason" value="{{ old('reason') }}" maxlength="500"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                        <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                    </div>
                    <div>
                        <x-primary-button size="sm">{{ __('admin.shop_closures.submit_create') }}</x-primary-button>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                    <h2 class="text-sm font-semibold text-gray-900">{{ __('admin.shop_closures.section_upcoming') }}</h2>
                </div>
                @if ($upcoming->isEmpty())
                    <p class="p-6 text-sm text-gray-500">{{ __('admin.shop_closures.empty_upcoming') }}</p>
                @else
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-3">{{ __('admin.shop_closures.col_period') }}</th>
                                <th class="px-6 py-3">{{ __('admin.shop_closures.col_reason') }}</th>
                                <th class="px-6 py-3">{{ __('admin.shop_closures.col_created_by') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-800">
                            @foreach ($upcoming as $closure)
                                <tr>
                                    <td class="px-6 py-3 tabular-nums">
                                        @if ($closure->starts_on->eq($closure->ends_on))
                                            {{ $closure->starts_on->translatedFormat('d.m.Y') }}
                                        @else
                                            {{ $closure->starts_on->translatedFormat('d.m.Y') }}
                                            –
                                            {{ $closure->ends_on->translatedFormat('d.m.Y') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-gray-600">{{ $closure->reason ?: '—' }}</td>
                                    <td class="px-6 py-3 text-gray-500">{{ $closure->createdBy?->name ?? '—' }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <form method="post" action="{{ route('app.admin.shop-closures.destroy', $closure) }}"
                                              onsubmit="return confirm('{{ __('admin.shop_closures.delete_confirm') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 underline underline-offset-2 hover:no-underline">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($past->isNotEmpty())
                <details class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <summary class="cursor-pointer border-b border-gray-100 bg-gray-50 px-6 py-4 text-sm font-semibold text-gray-700">
                        {{ __('admin.shop_closures.section_past') }} ({{ $past->count() }})
                    </summary>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-3">{{ __('admin.shop_closures.col_period') }}</th>
                                <th class="px-6 py-3">{{ __('admin.shop_closures.col_reason') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-600">
                            @foreach ($past as $closure)
                                <tr>
                                    <td class="px-6 py-3 tabular-nums">
                                        @if ($closure->starts_on->eq($closure->ends_on))
                                            {{ $closure->starts_on->translatedFormat('d.m.Y') }}
                                        @else
                                            {{ $closure->starts_on->translatedFormat('d.m.Y') }}
                                            –
                                            {{ $closure->ends_on->translatedFormat('d.m.Y') }}
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">{{ $closure->reason ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </details>
            @endif

        </div>
    </div>
</x-admin-layout>
