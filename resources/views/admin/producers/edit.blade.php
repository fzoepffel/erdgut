<x-admin-layout>
    <x-slot name="breadcrumbs">
        <x-admin.breadcrumbs :items="[
            ['label' => __('admin.shell.overview'), 'href' => route('app.admin.dashboard')],
            ['label' => __('Producers'), 'href' => route('app.admin.producers.index')],
            ['label' => $producer->name],
        ]" />
    </x-slot>
    <x-slot name="header">
        <h1 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Edit producer') }}: {{ $producer->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800" role="status">
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @include('admin.producers._form', [
                        'producer' => $producer,
                        'action' => route('app.admin.producers.update', $producer),
                        'method' => 'put',
                    ])
                </div>
            </div>
        </div>
    </div>
</x-admin-layout>
