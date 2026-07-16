{{-- Weekly grouped bar chart; all geometry precomputed in the controller.
     Props: $chart (see ProductController::barChartGeometry), $heading,
     $unitSuffix, $testId. --}}
<div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm">
    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-gray-600">
        <span class="text-sm font-semibold text-gray-800">{{ $heading }}</span>
        @foreach ($chart['legend'] as $item)
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-sm" style="background: {{ $item['color'] }}"></span>
                {{ $item['label'] }}
            </span>
        @endforeach
        <span class="ml-auto text-gray-400">{{ $unitSuffix }}</span>
    </div>
    <svg viewBox="0 0 {{ $chart['w'] }} {{ $chart['h'] }}" class="w-full" role="img" data-testid="{{ $testId }}">
        @foreach ($chart['yTicks'] as $tick)
            <line x1="{{ $chart['padL'] }}" y1="{{ $tick['y'] }}" x2="{{ $chart['w'] - $chart['padR'] }}" y2="{{ $tick['y'] }}" stroke="#f0f0ed" stroke-width="1"/>
            <text x="{{ $chart['padL'] - 6 }}" y="{{ $tick['y'] + 3 }}" text-anchor="end" font-size="10" fill="#9ca3af">{{ $tick['label'] }}</text>
        @endforeach
        @foreach ($chart['groups'] as $group)
            <text x="{{ $group['labelX'] }}" y="{{ $chart['h'] - 8 }}" text-anchor="middle" font-size="9" fill="#9ca3af">{{ $group['label'] }}</text>
            @foreach ($group['bars'] as $bar)
                @if ($bar['h'] > 0)
                    <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] }}" width="{{ $bar['w'] }}" height="{{ $bar['h'] }}" fill="{{ $bar['color'] }}" rx="1">
                        <title>{{ $bar['title'] }}</title>
                    </rect>
                @endif
            @endforeach
        @endforeach
    </svg>
</div>
