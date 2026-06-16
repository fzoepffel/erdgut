@extends('layouts.shop-marketing')

@section('content')
    <section class="border-b border-brand-ink/10" aria-labelledby="shop-contact-hero-heading">
        <div class="mx-auto max-w-7xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8">
            <div class="max-w-4xl">
                <x-reveal variant="fade-up">
                    <p class="eyebrow text-chapter-fish">{{ __('Contact') }}</p>
                </x-reveal>
                <x-reveal variant="fade-up" delay="100">
                    <h1 id="shop-contact-hero-heading" class="mt-5 font-display text-5xl font-semibold leading-[1.05] tracking-tight text-brand-ink sm:text-6xl lg:text-7xl">
                        {{ __('Questions about the shop, or interested in working with us? Write to us.') }}
                    </h1>
                </x-reveal>
                <x-reveal variant="fade-up" delay="200">
                    <p class="mt-8 max-w-2xl text-lg leading-relaxed text-brand-ink/75">
                        {{ __('Send us a note and we’ll get back within about one to two business days. The more context you share, the faster we can help.') }}
                    </p>
                </x-reveal>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-3xl px-4 py-20 sm:px-6 sm:py-28 lg:px-8" aria-labelledby="shop-contact-form-heading">
        <h2 id="shop-contact-form-heading" class="sr-only">{{ __('Contact form') }}</h2>

        @if (session('status') === 'contact-submitted')
            <div
                id="shop-contact-success"
                class="mb-12 border border-chapter-produce/40 bg-chapter-produce/5 p-8 text-brand-ink"
                role="status"
                tabindex="-1"
                data-testid="shop-contact-success"
            >
                <p class="eyebrow text-chapter-produce">{{ __('Message received') }}</p>
                <h3 class="mt-4 font-display text-2xl text-brand-ink">{{ __('We\'ll be in touch soon.') }}</h3>
                <ul class="mt-4 list-inside list-disc space-y-2 text-sm leading-relaxed text-brand-ink/75">
                    <li>{{ __('Your message is in our inbox and we’ve logged it for the team.') }}</li>
                    <li>{{ __('We usually reply within one to two business days.') }}</li>
                    <li>{{ __('If you don’t see a reply, check your spam or promotions folder.') }}</li>
                </ul>
                <p class="mt-6 text-sm text-brand-ink/80">
                    <a href="{{ route('public.home') }}" class="font-semibold underline-offset-4 hover:underline">{{ __('Back to homepage') }}</a>
                    <span class="mx-2 text-brand-ink/40">·</span>
                    <a href="{{ route('public.shop.index') }}" class="font-semibold underline-offset-4 hover:underline">{{ __('Visit the shop') }}</a>
                </p>
            </div>
        @endif

        <x-reveal variant="fade-up">
            <form
                action="{{ route('public.shop-contact.store') }}"
                method="POST"
                class="space-y-10 border border-brand-ink/15 bg-brand-cream/40 p-8 sm:p-10"
                data-testid="shop-contact-form"
                x-data="{ sending: false }"
                @submit="sending = true"
            >
                @csrf

                <fieldset class="space-y-6">
                    <legend class="eyebrow text-chapter-produce">{{ __('Your details') }}</legend>

                    <div>
                        <label for="name" class="block text-sm font-medium text-brand-ink">{{ __('Name') }}</label>
                        <input
                            id="name"
                            name="name"
                            type="text"
                            value="{{ old('name') }}"
                            required
                            autocomplete="name"
                            @class([
                                'mt-1 block w-full border-0 border-b bg-transparent px-0 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:outline-none focus:ring-0',
                                'border-brand-ink/30 focus:border-chapter-produce' => ! $errors->has('name'),
                                'border-chapter-meat focus:border-chapter-meat' => $errors->has('name'),
                            ])
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}"
                            @if ($errors->has('name')) aria-describedby="name-error" @endif
                        >
                        @error('name')
                            <p id="name-error" class="mt-2 text-sm text-chapter-meat" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-brand-ink">{{ __('Email') }}</label>
                        <input
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            required
                            autocomplete="email"
                            inputmode="email"
                            @class([
                                'mt-1 block w-full border-0 border-b bg-transparent px-0 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:outline-none focus:ring-0',
                                'border-brand-ink/30 focus:border-chapter-produce' => ! $errors->has('email'),
                                'border-chapter-meat focus:border-chapter-meat' => $errors->has('email'),
                            ])
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                            @if ($errors->has('email')) aria-describedby="email-error" @endif
                        >
                        <p class="mt-2 text-xs text-brand-ink/60">{{ __('We’ll only use this address to reply to your message.') }}</p>
                        @error('email')
                            <p id="email-error" class="mt-2 text-sm text-chapter-meat" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </fieldset>

                <fieldset class="space-y-6">
                    <legend class="eyebrow text-chapter-produce">{{ __('Your message') }}</legend>

                    <div>
                        <label for="subject" class="block text-sm font-medium text-brand-ink">{{ __('Subject') }} <span class="font-normal text-brand-ink/50">({{ __('optional') }})</span></label>
                        <input
                            id="subject"
                            name="subject"
                            type="text"
                            value="{{ old('subject') }}"
                            autocomplete="off"
                            @class([
                                'mt-1 block w-full border-0 border-b bg-transparent px-0 py-2.5 text-base text-brand-ink placeholder:text-brand-ink/40 focus:outline-none focus:ring-0',
                                'border-brand-ink/30 focus:border-chapter-produce' => ! $errors->has('subject'),
                                'border-chapter-meat focus:border-chapter-meat' => $errors->has('subject'),
                            ])
                            aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}"
                            @if ($errors->has('subject')) aria-describedby="subject-error" @endif
                        >
                        @error('subject')
                            <p id="subject-error" class="mt-2 text-sm text-chapter-meat" role="alert">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="message" class="block text-sm font-medium text-brand-ink">{{ __('Message') }}</label>
                        <textarea
                            id="message"
                            name="message"
                            rows="6"
                            required
                            autocomplete="off"
                            @class([
                                'mt-1 block w-full border bg-brand-cream/60 px-4 py-3 text-base text-brand-ink placeholder:text-brand-ink/40 focus:outline-none focus:ring-0',
                                'border-brand-ink/30 focus:border-chapter-produce' => ! $errors->has('message'),
                                'border-chapter-meat focus:border-chapter-meat' => $errors->has('message'),
                            ])
                            aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}"
                            @if ($errors->has('message')) aria-describedby="message-error" @endif
                        >{{ old('message') }}</textarea>
                        @error('message')
                            <p id="message-error" class="mt-2 text-sm text-chapter-meat" role="alert">{{ $message }}</p>
                        @enderror
                    </div>
                </fieldset>

                <p class="text-sm leading-relaxed text-brand-ink/70">
                    {{ __('We store your message so our team can respond and improve support. We don’t use it for marketing unless you ask us to.') }}
                    {{ __('Learn more about how we work on our') }}
                    <a href="{{ route('public.shop-about') }}" class="font-medium text-brand-ink underline-offset-4 hover:underline">{{ __('About') }}</a> {{ __('page.') }}
                </p>

                <div class="hidden" aria-hidden="true">
                    <label for="website">{{ __('Website') }}</label>
                    <input id="website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>

                <div class="flex flex-col gap-4 border-t border-brand-ink/15 pt-6 sm:flex-row sm:items-center sm:justify-between">
                    <button
                        type="submit"
                        class="btn-solid"
                        data-testid="shop-contact-submit"
                        :disabled="sending"
                    >
                        <span x-text="sending ? '{{ __('Sending…') }}' : '{{ __('Send message') }}'"></span>
                    </button>
                    <p class="text-xs text-brand-ink/55 sm:max-w-xs sm:text-right">
                        {{ __('If the form doesn’t go through, wait a minute and try again — we limit rapid submissions to reduce spam.') }}
                    </p>
                </div>
            </form>
        </x-reveal>
    </section>
@endsection
