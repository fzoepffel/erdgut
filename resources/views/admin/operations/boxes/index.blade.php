<x-admin-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('admin.box_setup.title') }}
            </h2>
            <a href="{{ route('app.admin.products.weekly.show') }}" class="text-sm font-medium text-emerald-700 hover:underline">
                {{ __('admin.box_setup.link_weekly_catalog') }}
            </a>
        </div>
    </x-slot>

    <div class="py-8 sm:py-12">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <p class="text-sm text-gray-600">
                {{ __('admin.box_setup.intro') }}
            </p>

            {{-- Single source of truth for the weekly planner: the compact grid
                 table. Until now we also rendered an 8-card grid below it
                 with the same data at lower density — caused vertical bloat
                 and split admin attention. The table has clickable rows + a
                 secondary "view boxes" path on the per-week detail page,
                 which covers everything the card grid offered. --}}
            @include('admin.operations.boxes._planner_compact_grid', ['weeks' => $weeks])
        </div>
    </div>
</x-admin-layout>
