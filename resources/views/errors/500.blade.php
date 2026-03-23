@extends('layouts.marketing')

@section('content')
<div class="flex min-h-[60vh] items-center justify-center px-4">
    <div class="max-w-md text-center">
        <p class="text-6xl font-bold text-emerald-700">500</p>
        <h1 class="mt-4 text-2xl font-semibold text-gray-900">Etwas ist schiefgelaufen</h1>
        <p class="mt-2 text-gray-600">Bitte versuche es erneut oder kontaktiere uns, wenn das Problem weiterhin besteht.</p>
        <div class="mt-6 flex items-center justify-center gap-4">
            <a href="{{ url()->previous() }}" class="inline-block rounded-md border border-emerald-700 px-6 py-3 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50">
                Erneut versuchen
            </a>
            <a href="{{ route('public.contact') }}" class="inline-block rounded-md bg-emerald-700 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-800">
                Kontakt
            </a>
        </div>
    </div>
</div>
@endsection
