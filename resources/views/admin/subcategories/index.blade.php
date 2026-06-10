<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-xl font-semibold leading-tight text-gray-800">
                {{ __('admin.subcategories.title') }}
            </h1>
            <a href="{{ route('app.admin.subcategories.create') }}" class="btn-solid-sm">
                {{ __('admin.subcategories.add') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="rounded-md bg-emerald-50 p-4 text-sm text-emerald-800" role="status">
                    {{ session('status') }}
                </div>
            @endif
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @foreach ($subcategories as $categoryValue => $group)
                <div class="overflow-hidden rounded-lg border border-gray-100 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-6 py-3">
                        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-500">
                            {{ $categoryLabels[$categoryValue] ?? $categoryValue }}
                        </h2>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500">
                                <th class="px-6 py-3">DE</th>
                                <th class="px-6 py-3">EN</th>
                                <th class="px-6 py-3">FR</th>
                                <th class="px-6 py-3 text-right">{{ __('Sort') }}</th>
                                <th class="px-6 py-3 text-right">{{ __('Products') }}</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 text-gray-800">
                            @foreach ($group as $sub)
                                <tr>
                                    <td class="px-6 py-3 font-medium">{{ $sub->label_de }}</td>
                                    <td class="px-6 py-3 text-gray-500">{{ $sub->label_en }}</td>
                                    <td class="px-6 py-3 text-gray-500">{{ $sub->label_fr }}</td>
                                    <td class="px-6 py-3 text-right text-gray-400">{{ $sub->sort_order }}</td>
                                    <td class="px-6 py-3 text-right text-gray-400">{{ $sub->products_count ?? $sub->products()->count() }}</td>
                                    <td class="px-6 py-3 text-right">
                                        <div class="flex items-center justify-end gap-4">
                                            <a href="{{ route('app.admin.subcategories.edit', $sub) }}"
                                               class="text-xs text-emerald-700 underline underline-offset-2 hover:no-underline">
                                                {{ __('Edit') }}
                                            </a>
                                            <form method="post" action="{{ route('app.admin.subcategories.destroy', $sub) }}"
                                                  onsubmit="return confirm('{{ __('admin.subcategories.delete_confirm', ['name' => $sub->label_de]) }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs text-red-600 underline underline-offset-2 hover:no-underline">
                                                    {{ __('Delete') }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach

            @if ($subcategories->isEmpty())
                <div class="rounded-lg border border-gray-100 bg-white p-8 text-center text-sm text-gray-500 shadow-sm">
                    {{ __('admin.subcategories.empty') }}
                </div>
            @endif

        </div>
    </div>
</x-admin-layout>
