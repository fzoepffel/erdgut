<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('app.admin.products.edit', $product) }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; {{ __('admin.price_history.back') }}</a>
            <h1 class="text-xl font-semibold leading-tight text-gray-800">{{ __('admin.price_history.heading', ['name' => $product->name]) }}</h1>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">

            @if ($variantOptions->isNotEmpty())
                {{-- Grade selector: plain links keep it dependency-free. --}}
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <span class="text-gray-500">{{ __('admin.price_history.grade') }}:</span>
                    <a href="{{ route('app.admin.products.price-history', $product) }}"
                       class="rounded-md px-2.5 py-1 {{ $variantId === null ? 'bg-emerald-700 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                        {{ __('admin.price_history.grade_base') }}
                    </a>
                    @foreach ($variantOptions as $variant)
                        <a href="{{ route('app.admin.products.price-history', [$product, 'variant' => $variant->id]) }}"
                           class="rounded-md px-2.5 py-1 {{ $variantId === $variant->id ? 'bg-emerald-700 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                            {{ $variant->localizedName() }}
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
                @if ($chart === null)
                    <p class="py-10 text-center text-sm text-gray-500" data-testid="price-history-empty">{{ __('admin.price_history.empty') }}</p>
                @else
                    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-gray-600" data-testid="price-history-legend">
                        @foreach ($series as $s)
                            <span class="inline-flex items-center gap-1.5">
                                <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $s['color'] }}"></span>
                                {{ $s['label'] }}
                            </span>
                        @endforeach
                        <span class="ml-auto text-gray-400">€ / {{ $unitLabel }}</span>
                    </div>

                    {{-- Server-rendered step chart: list prices hold until the
                         next change, so steps (not slopes) are the honest shape. --}}
                    <svg viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" class="w-full" role="img" data-testid="price-history-chart">
                        @foreach ($chart['yTicks'] as $tick)
                            <line x1="{{ $chart['padL'] }}" y1="{{ $tick['y'] }}" x2="{{ $chart['w'] - $chart['padR'] }}" y2="{{ $tick['y'] }}" stroke="#f0f0ed" stroke-width="1"/>
                            <text x="{{ $chart['padL'] - 6 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end" font-size="10" fill="#9ca3af">{{ $tick['label'] }}</text>
                        @endforeach
                        @foreach ($chart['xTicks'] as $tick)
                            <text x="{{ $tick['x'] }}" y="{{ $chart['h'] - 8 }}" text-anchor="middle" font-size="10" fill="#9ca3af">{{ $tick['label'] }}</text>
                        @endforeach
                        @foreach ($series as $s)
                            <path d="{{ $s['path'] }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="2" stroke-linejoin="round"/>
                            @foreach ($s['points'] as $p)
                                @if ($p['date'] !== null)
                                    <circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="3" fill="{{ $s['color'] }}">
                                        <title>{{ $s['label'] }}: {{ number_format($p['v'], 2, ',', '.') }} € / {{ $unitLabel }} ({{ $p['date'] }})</title>
                                    </circle>
                                @endif
                            @endforeach
                        @endforeach
                    </svg>
                @endif
            </div>

            @if ($entries->isNotEmpty())
                <div class="overflow-x-auto rounded-lg border border-gray-100 bg-white shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-600">
                                <th class="px-4 py-2">{{ __('admin.price_history.col_date') }}</th>
                                <th class="px-4 py-2">{{ __('admin.price_history.col_series') }}</th>
                                <th class="px-4 py-2 text-right">{{ __('admin.price_history.col_price') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($entries as $entry)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600">{{ $entry->recorded_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ $entryLabel($entry) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $entry->price, 2, ',', '.') }} € / {{ $unitLabel }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <p class="px-1 text-xs text-gray-400">{{ __('admin.price_history.note') }}</p>
        </div>
    </div>
</x-admin-layout>
