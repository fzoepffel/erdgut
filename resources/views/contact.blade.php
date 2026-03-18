@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
            <div class="max-w-3xl space-y-6">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-700">
                    Contact
                </p>
                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Get in touch with the Erdgut team.
                </h1>
                <p class="text-lg leading-8 text-gray-600">
                    Send us your questions about subscriptions, deliveries, or producer partnerships.
                    We usually reply within one to two business days.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 pb-16 sm:px-6 lg:px-8">
        @if (session('status') === 'contact-submitted')
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                Thanks for reaching out. Your message has been received.
            </div>
        @endif

        <form action="{{ route('public.contact.store') }}" method="POST" class="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="subject" class="block text-sm font-medium text-gray-700">Subject (optional)</label>
                <input id="subject" name="subject" type="text" value="{{ old('subject') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
                @error('subject')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                <textarea id="message" name="message" rows="6" required class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">{{ old('message') }}</textarea>
                @error('message')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <button type="submit" class="inline-flex items-center rounded-md bg-emerald-700 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800">
                    Send message
                </button>
            </div>
        </form>
    </section>
@endsection
