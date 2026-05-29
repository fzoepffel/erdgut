@php
    $locale       = app()->getLocale();
    $memberSince  = $user->created_at->locale($locale)->isoFormat('MMMM YYYY');
    $memberSinceD = $user->created_at->locale($locale)->isoFormat('D MMMM YYYY');
    $profileSaved  = session('status') === 'profile-updated';
    $passwordSaved = session('status') === 'password-updated';
@endphp

<x-app-layout :hide-app-footer="true">

    {{-- ═══════════════════════════════════════════
         HERO
    ═══════════════════════════════════════════ --}}
    <section class="relative border-b border-brand-ink/10 py-16 lg:py-24">

        {{-- Top-right tag --}}
        <span class="absolute right-6 top-6 hidden font-mono text-xs tracking-widest text-brand-ink/40 lg:block" aria-hidden="true">
            {{ __('customer.account.hero_tag', ['date' => $memberSince]) }}
        </span>

        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:items-start">

                {{-- Left --}}
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/40">{{ __('customer.account.hero_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h1 class="mt-6 font-display text-5xl leading-[1.02] tracking-tight text-balance text-brand-ink md:text-6xl lg:text-[5.5rem]">
                        {{ __('customer.account.hero_h1_pre') }}<em class="font-normal italic text-chapter-produce">{{ __('customer.account.hero_h1_em') }}</em>{{ __('customer.account.hero_h1_suf') }}
                    </h1></x-reveal>
                    <x-reveal variant="fade-up" delay="200"><p class="mt-8 max-w-xl text-base leading-relaxed text-brand-ink/55">
                        {{ __('customer.account.hero_body') }}
                    </p></x-reveal>
                </div>

                {{-- Right: "On the slip" panel --}}
                <x-reveal variant="fade-up" delay="150"><div class="border border-brand-ink/10 bg-brand-paper p-8 lg:p-10">
                    <p class="eyebrow text-chapter-produce text-[0.7rem] tracking-widest">{{ __('customer.account.panel_label') }}</p>
                    <p class="mt-2 font-display text-3xl text-brand-ink">{{ $user->name }}</p>

                    <div class="mt-6 h-px bg-brand-ink/10"></div>

                    <dl class="mt-6 space-y-4 text-sm">
                        <div class="flex items-baseline justify-between gap-6">
                            <dt class="font-mono text-[0.7rem] uppercase tracking-widest text-brand-ink/45 shrink-0">{{ __('customer.account.panel_since_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ $memberSinceD }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6">
                            <dt class="font-mono text-[0.7rem] uppercase tracking-widest text-brand-ink/45 shrink-0">{{ __('customer.account.panel_signin_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ __('customer.account.panel_signin_value') }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6">
                            <dt class="font-mono text-[0.7rem] uppercase tracking-widest text-brand-ink/45 shrink-0">{{ __('customer.account.panel_last_signin_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ $user->updated_at->locale($locale)->isoFormat('ddd, HH:mm') }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6">
                            <dt class="font-mono text-[0.7rem] uppercase tracking-widest text-brand-ink/45 shrink-0">{{ __('customer.account.panel_from_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink text-right">{{ $user->primaryAddress()?->first()?->city ?? 'Berlin' }}</dd>
                        </div>
                    </dl>
                </div></x-reveal>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════
         § 01  PROFILE & CONTACT
    ═══════════════════════════════════════════ --}}
    <section class="border-b border-brand-ink/10 bg-brand-paper py-20 lg:py-28"
        x-data="{
            saved: {{ $profileSaved ? 'true' : 'false' }},
            init() { if (this.saved) setTimeout(() => { this.saved = false; }, 2400); }
        }"
    >
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Section header --}}
            <div class="mb-14 grid gap-6 md:grid-cols-[auto_1fr_1fr] md:items-end md:gap-12">
                <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">01</span>
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/35">{{ __('customer.account.profile_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('customer.account.profile_h2') }}
                    </h2></x-reveal>
                </div>
                <x-reveal variant="fade-up" delay="200"><p class="max-w-sm text-sm leading-relaxed text-brand-ink/50 md:text-right md:justify-self-end">
                    {{ __('customer.account.profile_lede') }}
                </p></x-reveal>
            </div>

            <form method="POST" action="{{ route('account.profile.update') }}">
                @csrf
                @method('PATCH')

                <div class="grid grid-cols-1 gap-x-12 gap-y-10 md:grid-cols-12">

                    {{-- NAME (full width) --}}
                    <div class="md:col-span-12">
                        <label class="flex flex-col">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.name_label') }}</span>
                            <input
                                type="text"
                                name="name"
                                value="{{ old('name', $user->name) }}"
                                required
                                class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                            >
                        </label>
                        @error('name')
                            <p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- PHONE --}}
                    <div class="md:col-span-6">
                        <label class="flex flex-col">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.phone_label') }}</span>
                            <input
                                type="tel"
                                name="phone"
                                value="{{ old('phone', $user->phone) }}"
                                class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                            >
                        </label>
                        <p class="mt-3 max-w-md text-xs leading-relaxed text-brand-ink/45">{{ __('customer.account.phone_help') }}</p>
                        @error('phone')
                            <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- EMAIL --}}
                    <div class="md:col-span-6">
                        <label class="flex flex-col">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.email_label') }}</span>
                            <input
                                type="email"
                                name="email"
                                value="{{ old('email', $user->email) }}"
                                required
                                class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                            >
                        </label>
                        <p class="mt-3 max-w-md text-xs leading-relaxed text-brand-ink/45">{{ __('customer.account.email_help') }}</p>
                        @error('email')
                            <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                {{-- Save row --}}
                <x-reveal variant="fade-up" delay="300"><div class="mt-14 flex items-center gap-6">
                    <span
                        aria-live="polite"
                        class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce transition-opacity duration-300"
                        :class="saved ? 'opacity-100' : 'opacity-0'"
                    >{{ __('customer.account.saved_chip') }}</span>
                    <a href="{{ route('account.profile.edit') }}"
                       class="text-sm text-brand-ink/45 underline underline-offset-4 hover:text-brand-ink transition-colors">
                        {{ __('customer.account.discard_button') }}
                    </a>
                    <button type="submit"
                            class="rounded-sm bg-brand-ink px-7 py-3 text-sm text-brand-paper hover:bg-brand-ink/85 transition-colors">
                        {{ __('customer.account.save_button') }}
                    </button>
                </div></x-reveal>

            </form>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════
         § 02  SIGN-IN SECURITY
    ═══════════════════════════════════════════ --}}
    <section class="border-b border-brand-ink/10 py-20 lg:py-28"
        x-data="{
            saved: {{ $passwordSaved ? 'true' : 'false' }},
            init() { if (this.saved) setTimeout(() => { this.saved = false; }, 2400); }
        }"
    >
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Section header --}}
            <div class="mb-14 grid gap-6 md:grid-cols-[auto_1fr_1fr] md:items-end md:gap-12">
                <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">02</span>
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/35">{{ __('customer.account.security_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('customer.account.security_h2') }}
                    </h2></x-reveal>
                </div>
                <x-reveal variant="fade-up" delay="200"><p class="max-w-sm text-sm leading-relaxed text-brand-ink/50 md:text-right md:justify-self-end">
                    {{ __('customer.account.security_lede') }}
                </p></x-reveal>
            </div>

            <div class="grid gap-12 lg:grid-cols-[1fr_1.4fr] lg:gap-20">

                {{-- Left: pull-quote + stats --}}
                <div>
                    <x-reveal variant="fade-up" delay="100"><div class="border-l-2 border-chapter-produce pl-5">
                        <p class="eyebrow text-chapter-produce text-[0.65rem] tracking-widest">{{ __('customer.account.password_quote_label') }}</p>
                        <blockquote class="mt-3 font-display text-xl italic leading-snug text-brand-ink/80">
                            "{{ __('customer.account.password_quote') }}"
                        </blockquote>
                    </div></x-reveal>

                    <dl class="mt-10 space-y-5 text-sm">
                        <div class="flex items-baseline justify-between gap-6 border-b border-brand-ink/10 pb-3">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/45">{{ __('customer.account.last_changed_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink">{{ $user->updated_at->locale($locale)->isoFormat('D MMM YYYY') }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 border-b border-brand-ink/10 pb-3">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/45">{{ __('customer.account.strength_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink">—</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-6 border-b border-brand-ink/10 pb-3">
                            <dt class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/45">{{ __('customer.account.two_step_label') }}</dt>
                            <dd class="font-display text-lg text-brand-ink">{{ __('customer.account.two_step_value') }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Right: password form --}}
                <div>
                    <form method="POST" action="{{ route('password.update') }}" class="grid gap-10">
                        @csrf
                        @method('PUT')

                        {{-- CURRENT PASSWORD --}}
                        <div>
                            <label class="flex flex-col">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.current_password_label') }}</span>
                                <input
                                    type="password"
                                    name="current_password"
                                    autocomplete="current-password"
                                    class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                >
                            </label>
                            @error('current_password', 'updatePassword')
                                <p class="mt-2 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- NEW PASSWORD --}}
                        <div>
                            <label class="flex flex-col">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.new_password_label') }}</span>
                                <input
                                    type="password"
                                    name="password"
                                    autocomplete="new-password"
                                    class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                >
                            </label>
                            <p class="mt-3 text-xs leading-relaxed text-brand-ink/45">{{ __('customer.account.new_password_help') }}</p>
                            @error('password', 'updatePassword')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- CONFIRM NEW PASSWORD --}}
                        <div>
                            <label class="flex flex-col">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.confirm_password_label') }}</span>
                                <input
                                    type="password"
                                    name="password_confirmation"
                                    autocomplete="new-password"
                                    class="mt-3 w-full border-b border-brand-ink/15 bg-transparent pb-2 font-display text-2xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-produce focus:outline-none transition-colors"
                                >
                            </label>
                            @error('password_confirmation', 'updatePassword')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Save row --}}
                        <x-reveal variant="fade-up" delay="300"><div class="flex items-center gap-6 pt-4">
                            <span
                                aria-live="polite"
                                class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce transition-opacity duration-300"
                                :class="saved ? 'opacity-100' : 'opacity-0'"
                            >{{ __('customer.account.saved_chip') }}</span>
                            <a href="{{ route('account.profile.edit') }}"
                               class="text-sm text-brand-ink/45 underline underline-offset-4 hover:text-brand-ink transition-colors">
                                {{ __('customer.account.discard_button') }}
                            </a>
                            <button type="submit"
                                    class="rounded-sm bg-brand-ink px-7 py-3 text-sm text-brand-paper hover:bg-brand-ink/85 transition-colors">
                                {{ __('customer.account.save_button') }}
                            </button>
                        </div></x-reveal>

                    </form>
                </div>

            </div>
        </div>
    </section>


    {{-- ═══════════════════════════════════════════
         § 03  THE QUIET ROOM
    ═══════════════════════════════════════════ --}}
    <section class="border-b border-brand-ink/10 py-20 lg:py-28">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">

            {{-- Section header --}}
            <div class="mb-14 grid gap-6 md:grid-cols-[auto_1fr_1fr] md:items-end md:gap-12">
                <span class="font-mono text-xs uppercase tracking-widest text-chapter-produce">03</span>
                <div>
                    <x-reveal variant="fade-up"><p class="eyebrow text-brand-ink/35">{{ __('customer.account.danger_eyebrow') }}</p></x-reveal>
                    <x-reveal variant="fade-up" delay="100"><h2 class="mt-4 font-display text-4xl text-balance text-brand-ink md:text-5xl">
                        {{ __('customer.account.danger_h2') }}
                    </h2></x-reveal>
                </div>
                <x-reveal variant="fade-up" delay="200"><p class="max-w-sm text-sm leading-relaxed text-brand-ink/50 md:text-right md:justify-self-end">
                    {{ __('customer.account.danger_lede') }}
                </p></x-reveal>
            </div>

            <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">

                {{-- Card A: Pause (subscriber only — admins have no subscription) --}}
                @unless($user->is_admin)
                <x-reveal variant="fade-up" delay="100"><div class="border border-brand-ink/15 bg-brand-paper p-8 lg:p-10">
                    <p class="eyebrow text-chapter-produce text-[0.65rem] tracking-widest">{{ __('customer.account.pause_label') }}</p>
                    <h3 class="mt-3 font-display text-3xl text-brand-ink">{{ __('customer.account.pause_h3') }}</h3>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-brand-ink/55">
                        {{ __('customer.account.pause_body') }}
                    </p>
                    <form method="POST" action="{{ route('app.subscriptions.pause') }}" class="mt-8">
                        @csrf
                        <button type="submit"
                                class="rounded-sm border border-brand-ink/20 bg-transparent px-6 py-3 text-sm text-brand-ink hover:border-brand-ink/40 hover:bg-brand-ink/5 transition-colors">
                            {{ __('customer.account.pause_button') }}
                        </button>
                    </form>
                </div></x-reveal>
                @endunless

                {{-- Card B: Delete account --}}
                <x-reveal variant="fade-up" delay="150"><div class="border border-chapter-meat/40 bg-chapter-meat/5 p-8 lg:p-10"
                     x-data="{ confirming: false }">
                    <p class="eyebrow text-chapter-meat text-[0.65rem] tracking-widest">{{ __('customer.account.delete_label') }}</p>
                    <h3 class="mt-3 font-display text-3xl text-brand-ink">{{ __('customer.account.delete_h3') }}</h3>
                    <p class="mt-4 max-w-md text-sm leading-relaxed text-brand-ink/55">
                        {{ __('customer.account.delete_body') }}
                    </p>

                    {{-- Show confirm form when user clicks --}}
                    <div x-show="!confirming" class="mt-8">
                        <button type="button"
                                @click="confirming = true"
                                class="rounded-sm bg-chapter-meat px-6 py-3 text-sm text-white hover:bg-chapter-meat/90 transition-colors">
                            {{ __('customer.account.delete_button') }}
                        </button>
                    </div>

                    <form x-show="confirming" x-cloak
                          method="POST" action="{{ route('account.profile.destroy') }}"
                          class="mt-8 space-y-4">
                        @csrf
                        @method('DELETE')

                        <div>
                            <label class="flex flex-col">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-brand-ink/40">{{ __('customer.account.delete_confirm_label') }}</span>
                                <input
                                    type="password"
                                    name="password"
                                    autocomplete="current-password"
                                    class="mt-2 w-full border-b border-chapter-meat/30 bg-transparent pb-2 font-display text-xl text-brand-ink placeholder:text-brand-ink/25 focus:border-chapter-meat focus:outline-none transition-colors"
                                    placeholder="{{ __('customer.account.delete_confirm_help') }}"
                                >
                            </label>
                            @error('password', 'userDeletion')
                                <p class="mt-1 text-xs text-chapter-meat">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-4 pt-2">
                            <button type="button"
                                    @click="confirming = false"
                                    class="text-sm text-brand-ink/45 underline underline-offset-4 hover:text-brand-ink transition-colors">
                                {{ __('customer.account.discard_button') }}
                            </button>
                            <button type="submit"
                                    class="rounded-sm bg-chapter-meat px-6 py-3 text-sm text-white hover:bg-chapter-meat/90 transition-colors">
                                {{ __('customer.account.delete_button') }}
                            </button>
                        </div>
                    </form>
                </div></x-reveal>

            </div>
        </div>
    </section>

    <x-customer.editorial-footer-band />

</x-app-layout>
