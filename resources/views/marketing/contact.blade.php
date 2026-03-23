@extends('layouts.marketing')

@section('content')
    <section class="bg-gradient-to-b from-emerald-50 to-white" aria-labelledby="contact-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <div class="max-w-3xl space-y-6">
                <p class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-sm font-semibold text-emerald-800">
                    Contact
                </p>
                <h1 id="contact-hero-heading" class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    We’re here to help with subscriptions, deliveries, and partnerships.
                </h1>
                <p class="text-lg leading-8 text-gray-600">
                    Send us a note and we’ll get back within about one to two business days. The more context you share, the faster we can help.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 pb-16 sm:px-6 lg:px-8" aria-labelledby="contact-form-heading">
        <h2 id="contact-form-heading" class="sr-only">Contact form</h2>

        @if (session('status') === 'contact-submitted')
            <div
                id="contact-success"
                class="mb-8 rounded-xl border border-emerald-200 bg-emerald-50 p-6 text-emerald-950 shadow-sm"
                role="status"
                tabindex="-1"
                data-testid="contact-success"
            >
                <h3 class="text-lg font-semibold text-emerald-950">Message received</h3>
                <ul class="mt-3 list-inside list-disc space-y-2 text-sm leading-relaxed text-emerald-900/95">
                    <li>Your message is in our inbox and we’ve logged it for the team.</li>
                    <li>We usually reply within one to two business days.</li>
                    <li>If you don’t see a reply, check your spam or promotions folder.</li>
                </ul>
                <p class="mt-4 text-sm text-emerald-900/90">
                    <a href="{{ route('public.home') }}" class="font-semibold text-emerald-900 underline-offset-4 hover:underline">Back to homepage</a>
                    <span class="text-emerald-800/60"> · </span>
                    <a href="{{ route('auth.register') }}" class="font-semibold text-emerald-900 underline-offset-4 hover:underline">Create an account</a>
                </p>
            </div>
        @endif

        <form
            action="{{ route('public.contact.store') }}"
            method="POST"
            class="space-y-8 rounded-xl border border-gray-200 bg-white p-6 shadow-sm sm:p-8"
            data-testid="contact-form"
            x-data="{ sending: false }"
            @submit="sending = true"
        >
            @csrf

            <fieldset class="space-y-5 rounded-lg border border-gray-100 bg-gray-50/50 p-5">
                <legend class="text-base font-semibold text-gray-900">Your details</legend>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input
                        id="name"
                        name="name"
                        type="text"
                        value="{{ old('name') }}"
                        required
                        autocomplete="name"
                        @class([
                            'mt-1 block w-full min-h-11 rounded-md border px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600',
                            'border-gray-300' => ! $errors->has('name'),
                            'border-red-500' => $errors->has('name'),
                        ])
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                        @if ($errors->has('name')) aria-describedby="name-error" @endif
                    >
                    @error('name')
                        <p id="name-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autocomplete="email"
                        inputmode="email"
                        @class([
                            'mt-1 block w-full min-h-11 rounded-md border px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600',
                            'border-gray-300' => ! $errors->has('email'),
                            'border-red-500' => $errors->has('email'),
                        ])
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                        @if ($errors->has('email')) aria-describedby="email-error" @endif
                    >
                    <p class="mt-1 text-xs text-gray-500">We’ll only use this address to reply to your message.</p>
                    @error('email')
                        <p id="email-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </fieldset>

            <fieldset class="space-y-5 rounded-lg border border-gray-100 bg-gray-50/50 p-5">
                <legend class="text-base font-semibold text-gray-900">Your message</legend>

                <div>
                    <label for="subject" class="block text-sm font-medium text-gray-700">Subject <span class="font-normal text-gray-500">(optional)</span></label>
                    <input
                        id="subject"
                        name="subject"
                        type="text"
                        value="{{ old('subject') }}"
                        autocomplete="off"
                        @class([
                            'mt-1 block w-full min-h-11 rounded-md border px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600',
                            'border-gray-300' => ! $errors->has('subject'),
                            'border-red-500' => $errors->has('subject'),
                        ])
                        aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                        @if ($errors->has('subject')) aria-describedby="subject-error" @endif
                    >
                    @error('subject')
                        <p id="subject-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="message" class="block text-sm font-medium text-gray-700">Message</label>
                    <textarea
                        id="message"
                        name="message"
                        rows="6"
                        required
                        autocomplete="off"
                        @class([
                            'mt-1 block w-full rounded-md border px-3 py-2.5 text-sm text-gray-900 shadow-sm focus:border-emerald-600 focus:outline-none focus:ring-1 focus:ring-emerald-600',
                            'border-gray-300' => ! $errors->has('message'),
                            'border-red-500' => $errors->has('message'),
                        ])
                        aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}"
                        @if ($errors->has('message')) aria-describedby="message-error" @endif
                    >{{ old('message') }}</textarea>
                    @error('message')
                        <p id="message-error" class="mt-1 text-sm text-red-600" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </fieldset>

            <p class="text-sm leading-6 text-gray-600">
                We store your message so our team can respond and improve support. We don’t use it for marketing unless you ask us to.
                Learn more about how we work on our
                <a href="{{ route('public.about') }}" class="font-medium text-emerald-800 underline-offset-4 hover:underline">About</a> page.
            </p>

            <div class="hidden" aria-hidden="true">
                <label for="website">Website</label>
                <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <button
                    type="submit"
                    class="inline-flex min-h-11 min-w-[10rem] items-center justify-center rounded-md bg-emerald-700 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-70"
                    data-testid="contact-submit"
                    :disabled="sending"
                >
                    <span x-text="sending ? 'Sending…' : 'Send message'"></span>
                </button>
                <p class="text-xs text-gray-500 sm:max-w-xs sm:text-right">
                    If the form doesn’t go through, wait a minute and try again — we limit rapid submissions to reduce spam.
                </p>
            </div>
        </form>
    </section>
@endsection
