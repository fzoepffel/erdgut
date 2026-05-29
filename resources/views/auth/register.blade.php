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
            <div class="grid gap-16 lg:grid-cols-[1.4fr_1fr] lg:gap-20 lg:items-start">

                {{-- Left: form --}}
                <div>

                    {{-- Section header — lede lives in the right column above the aside --}}
                    <x-reveal variant="fade-up">
                    <div class="mb-14 grid gap-6 md:grid-cols-[auto_1fr] md:items-end md:gap-12">
                        <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">01</span>
                        <div>
                            <p class="eyebrow text-brand-ink/35">{{ __('auth.register_section_eyebrow') }}</p>
                            <h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                                {{ __('auth.register_section_h2') }}
                            </h2>
                        </div>
                    </div>
                    </x-reveal>

                    <form method="POST" action="{{ route('auth.register.store') }}">
                        @csrf

                        {{-- Hairline-input stack: Name + Email full-width, PW pair side-by-side --}}
                        <div class="grid gap-y-10">

                            {{-- NAME --}}
                            <x-reveal variant="fade-up" delay="50">
                            <label class="flex flex-col">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Name') }}</span>
                                <input
                                    id="name" type="text" name="name"
                                    value="{{ old('name') }}"
                                    placeholder="{{ __('auth.register_name_placeholder') }}"
                                    required autofocus autocomplete="name"
                                    class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                >
                                @error('name')
                                    <p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </label>
                            </x-reveal>

                            {{-- EMAIL --}}
                            <x-reveal variant="fade-up" delay="100">
                            <label class="flex flex-col">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Email') }}</span>
                                <input
                                    id="email" type="email" name="email"
                                    value="{{ old('email') }}"
                                    placeholder="{{ __('auth.register_email_placeholder') }}"
                                    required autocomplete="username"
                                    class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                >
                                <p class="mt-3 text-xs leading-relaxed text-brand-ink/45">{{ __('auth.register_email_help') }}</p>
                                @error('email')
                                    <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                                @enderror
                            </label>
                            </x-reveal>

                            {{-- PASSWORD + CONFIRM side by side --}}
                            <x-reveal variant="fade-up" delay="150">
                            <div class="grid grid-cols-2 gap-x-12">
                                <label class="flex flex-col">
                                    <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Password') }}</span>
                                    <input
                                        id="password" type="password" name="password"
                                        placeholder="{{ __('auth.register_password_placeholder') }}"
                                        required autocomplete="new-password"
                                        class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                    >
                                    <p class="mt-3 text-xs leading-relaxed text-brand-ink/45">{{ __('auth.register_password_help') }}</p>
                                    @error('password')
                                        <p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>
                                    @enderror
                                </label>
                                <label class="flex flex-col">
                                    <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/35">{{ __('Confirm Password') }}</span>
                                    <input
                                        id="password_confirmation" type="password" name="password_confirmation"
                                        placeholder="{{ __('auth.register_confirm_placeholder') }}"
                                        required autocomplete="new-password"
                                        class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                    >
                                </label>
                            </div>
                            </x-reveal>

                        </div>

                        {{-- Submit row --}}
                        <x-reveal variant="fade-up" delay="200">
                        <div class="mt-10 flex flex-wrap items-center gap-6">
                            <button
                                type="submit"
                                class="rounded-sm bg-chapter-produce px-8 py-3 text-sm text-brand-cream hover:bg-chapter-produce/90 transition-colors"
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

                {{-- Right: lede above, then "What we keep" panel --}}
                <div class="flex flex-col gap-8 lg:gap-10">

                <x-reveal variant="fade" delay="100">
                <p class="text-sm leading-relaxed text-brand-ink/50">
                    {{ __('auth.register_section_lede') }}
                </p>
                </x-reveal>

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

                </div>{{-- end right flex column --}}

            </div>
        </div>
    </section>

@endsection

@section('footer')
    <x-customer.editorial-footer-band />
@endsection
