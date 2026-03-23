<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Producer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProducerController extends Controller
{
    /**
     * Show the public producers listing page.
     */
    public function index(Request $request): View
    {
        $regions = Producer::query()
            ->whereNotNull('region')
            ->where('region', '!=', '')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->values();

        $regionInput = $request->input('region');
        $regionNormalized = ($regionInput !== null && $regionInput !== '') ? (string) $regionInput : null;
        $qInput = $request->input('q');
        $qNormalized = ($qInput !== null && trim((string) $qInput) !== '') ? trim((string) $qInput) : null;

        $regionRules = ['nullable', 'string', 'max:255'];
        if ($regions->isNotEmpty()) {
            $regionRules[] = Rule::in($regions->all());
        }

        $validated = validator(
            [
                'region' => $regionNormalized,
                'q' => $qNormalized,
            ],
            [
                'region' => $regionRules,
                'q' => ['nullable', 'string', 'max:100'],
            ]
        )->validate();

        $query = Producer::query()->orderBy('name');

        if (! empty($validated['region'])) {
            $query->where('region', $validated['region']);
        }

        if (! empty($validated['q'])) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($validated['q']));
            $pattern = '%'.$escaped.'%';
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'like', $pattern)
                    ->orWhere('description', 'like', $pattern);
            });
        }

        $producers = $query->get();

        $hasAnyProducers = Producer::query()->exists();
        $filtersActive = ! empty($validated['region']) || ! empty($validated['q']);

        return view('marketing.producers', [
            'producers' => $producers,
            'regions' => $regions,
            'filters' => [
                'region' => $validated['region'] ?? '',
                'q' => $validated['q'] ?? '',
            ],
            'hasAnyProducers' => $hasAnyProducers,
            'filtersActive' => $filtersActive,
            'shopExtrasEnabled' => config('features.shop_extras_enabled', true),
        ]);
    }
}
