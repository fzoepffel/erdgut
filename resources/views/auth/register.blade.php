@extends('layouts.marketing')

@section('content')

    {{-- ═══════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════ --}}
    <section class="relative border-b border-brand-ink/10 py-16 lg:py-20">

        {{-- Step stamp top-right --}}
        <span class="absolute right-6 top-6 hidden font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35 lg:block" aria-hidden="true">
            {{ __('auth.register_step') }}
        </span>

        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <x-reveal><p class="eyebrow text-brand-ink/45">{{ __('auth.register_hero_eyebrow') }}</p></x-reveal>

            <x-reveal variant="fade-up" delay="100">
            <h1 class="mt-5 font-display text-5xl leading-[1.02] tracking-tight text-brand-ink md:text-6xl lg:text-7xl">
                {{ __('auth.register_hero_h1_pre') }}<em class="italic text-chapter-produce">{{ __('auth.register_hero_h1_em') }}</em>.
            </h1>
            </x-reveal>

            <x-reveal variant="fade-up" delay="200">
            <p class="mt-6 max-w-xl text-base leading-relaxed text-brand-ink/60">
                {{ __('auth.register_hero_body') }}
            </p>
            </x-reveal>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════
         FORM + PANEL
    ═══════════════════════════════════════════ --}}
    <section class="py-20 lg:py-28">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-16 lg:grid-cols-[1fr_360px] lg:gap-20 lg:items-start">

                {{-- Left: form --}}
                <div>

                    {{-- Section header --}}
                    <x-reveal variant="fade-up">
                    <div class="mb-14 grid gap-6 md:grid-cols-[auto_1fr_1fr] md:items-end md:gap-12">
                        <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">01</span>
                        <div>
                            <p class="eyebrow text-brand-ink/35">{{ __('auth.register_section_eyebrow') }}</p>
                            <h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                                {{ __('auth.register_section_h2') }}
                            </h2>
                        </div>
                        <p class="max-w-sm text-sm leading-relaxed text-brand-ink/50 md:text-right md:justify-self-end">
                            {{ __('auth.register_section_lede') }}
                        </p>
                    </div>
                    </x-reveal>

                    <form method="POST" action="{{ route('auth.register.store') }}">
                        @csrf

                        <div class="space-y-0 divide-y divide-brand-ink/10 border-t border-brand-ink/10">

                            {{-- NAME --}}
                            <x-reveal variant="fade-up" delay="50">
                            <div class="py-7">
                                <label for="name" class="block font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35 mb-2">
                                    {{ __('Name') }}
                                </label>
                                <input
                                    id="name" type="text" name="name"
                                    value="{{ old('name') }}"
                                    placeholder="{{ __('auth.register_name_placeholder') }}"
                                    required autofocus autocomplete="name"
                                    class="block w-full bg-transparent py-1 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30 transition-colors"
                                >
                                @error('name')
                                    <p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </div>
                            </x-reveal>

                            {{-- EMAIL --}}
                            <x-reveal variant="fade-up" delay="100">
                            <div class="py-7">
                                <label for="email" class="block font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35 mb-2">
                                    {{ __('Email') }}
                                </label>
                                <input
                                    id="email" type="email" name="email"
                                    value="{{ old('email') }}"
                                    placeholder="{{ __('auth.register_email_placeholder') }}"
                                    required autocomplete="username"
                                    class="block w-full bg-transparent py-1 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30 transition-colors"
                                >
                                <p class="mt-3 text-xs leading-relaxed text-brand-ink/45">{{ __('auth.register_email_help') }}</p>
                                @error('email')
                                    <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </div>
                            </x-reveal>

                            {{-- PASSWORD + CONFIRM --}}
                            <x-reveal variant="fade-up" delay="150">
                            <div class="py-7">
                                <div class="grid grid-cols-1 gap-8 sm:grid-cols-2">
                                    <div>
                                        <label for="password" class="block font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35 mb-2">
                                            {{ __('Password') }}
                                        </label>
                                        <input
                                            id="password" type="password" name="password"
                                            placeholder="{{ __('auth.register_password_placeholder') }}"
                                            required autocomplete="new-password"
                                            class="block w-full bg-transparent py-1 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30 transition-colors"
                                        >
                                        @error('password')
                                            <p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div>
                                        <label for="password_confirmation" class="block font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35 mb-2">
                                            {{ __('Confirm Password') }}
                                        </label>
                                        <input
                                            id="password_confirmation" type="password" name="password_confirmation"
                                            placeholder="{{ __('auth.register_confirm_placeholder') }}"
                                            required autocomplete="new-password"
                                            class="block w-full bg-transparent py-1 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:outline-none border-0 border-b border-transparent focus:border-brand-ink/30 transition-colors"
                                        >
                                    </div>
                                </div>
                                <p class="mt-3 text-xs leading-relaxed text-brand-ink/45">{{ __('auth.register_password_help') }}</p>
                            </div>
                            </x-reveal>

                        </div>

                        {{-- Submit row --}}
                        <x-reveal variant="fade-up" delay="200">
                        <div class="mt-10 flex flex-wrap items-center gap-6">
                            <button
                                type="submit"
                                class="rounded-sm bg-brand-ink px-8 py-3 text-sm text-brand-paper hover:bg-brand-ink/85 transition-colors"
                            >
                                {{ __('auth.register_submit') }}
                            </button>
                            <a
                                href="{{ route('auth.login') }}"
                                class="text-sm text-brand-ink/50 underline underline-offset-4 hover:text-brand-ink transition-colors"
                            >
                                {{ __('auth.register_login_link') }}
                            </a>
                        </div>
                        </x-reveal>

                    </form>
                </div>

                {{-- Right: "What we keep" panel --}}
                <x-reveal variant="fade" delay="150">
                <div class="border border-brand-ink/10 bg-brand-paper p-8 lg:p-10">
                    <p class="eyebrow text-[0.65rem] tracking-widest text-brand-ink/35">{{ __('auth.register_panel_label') }}</p>
                    <h3 class="mt-3 font-display text-3xl text-brand-ink">{{ __('auth.register_panel_h3') }}</h3>

                    <div class="mt-7 h-px bg-brand-ink/10"></div>

                    <dl class="mt-6 space-y-6">
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Name') }}</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-brand-ink/70">{{ __('auth.register_panel_name') }}</dd>
                        </div>
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Email') }}</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-brand-ink/70">{{ __('auth.register_panel_email') }}</dd>
                        </div>
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Password') }}</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-brand-ink/70">{{ __('auth.register_panel_password') }}</dd>
                        </div>
                        <div>
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Address') }}</dt>
                            <dd class="mt-1 text-sm leading-relaxed text-brand-ink/70">{{ __('auth.register_panel_address') }}</dd>
                        </div>
                    </dl>
                </div>
                </x-reveal>

            </div>
        </div>
    </section>

@endsection

@section('footer')
    <x-customer.editorial-footer-band />
@endsection
