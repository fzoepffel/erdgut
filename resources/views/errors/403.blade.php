@extends('layouts.marketing')

@section('content')
<div class="flex min-h-[60vh] items-center justify-center px-4">
    <div class="max-w-md text-center">
        <p class="text-6xl font-bold text-emerald-700">403</p>
        <h1 class="mt-4 text-2xl font-semibold text-gray-900">Zugriff verweigert</h1>
        <p class="mt-2 text-gray-600">Du hast keine Berechtigung, diese Seite aufzurufen.</p>
        @auth
            <a href="{{ route('app.dashboard') }}" class="mt-6 inline-block rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                Zum Dashboard
            </a>
        @else
            <a href="{{ route('public.home') }}" class="mt-6 inline-block rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                Zur Startseite
            </a>
        @endauth
    </div>
</div>
@endsection
