@php
    // W2-20: by the time the customer lands here their OnboardingDraft is
    // already cleared (PaymentStepController::storeComplete on success).
    // Load the freshly-created subscription + delivery context directly
    // from the User so the page can confirm what was just set up instead
    // of leaving the customer wondering whether anything stuck.
    /** @var \App\Models\User|null $authedUser */
    $authedUser = auth()->user();
    $subscription = $authedUser?->subscriptions()->latest('id')->first();
    $eggsAddOn = (bool) data_get($subscription?->preferences, 'eggs_dairy_add_on', false);
    $fulfillmentMode = $subscription?->fulfillment_mode?->value;
    $isPickup = $fulfillmentMode === \App\Enums\FulfillmentMode::Pickup->value;
    $address = $isPickup ? null : $authedUser?->primaryAddress;
    $pickup = config('box.pickup_location');
    $firstDelivery = $subscription?->firstExpectedDeliveryDate();

    // B3 (UX notes): when an unverified user is mid-onboarding (has a
    // draft, no finalised subscription) and lands here — typically
    // because they logged out and back in — surface a "Continue
    // setup" affordance. Without this they have no path back into
    // the wizard from the verify gate. The wizard itself doesn't
    // require email verification, so the link is safe to follow.
    $hasInflightDraft = $authedUser !== null
        && $subscription === null
        && app(\App\Services\Onboarding\OnboardingDraftManager::class)->userHasInFlightDraft($authedUser);

    $continueHref = $hasInflightDraft
        ? route('app.onboarding.start')
        : route('app.dashboard');
@endphp

@extends('layouts.marketing')

@section('content')

    {{-- ═══════════════════════════════════════════
         HERO + INSTRUCTIONS + ASIDE
    ═══════════════════════════════════════════ --}}
    <section class="relative border-b border-brand-ink/10 py-16 lg:py-24">

        {{-- Step stamp top-right --}}
        <span class="absolute right-6 top-6 hidden font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce lg:block" aria-hidden="true">
            {{ __('auth.verify_step') }}
        </span>

        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:gap-16 lg:items-start">

                {{-- ── Left: hero + steps ── --}}
                <div>
                    <x-reveal><p class="eyebrow text-chapter-produce">{{ __('auth.verify_eyebrow') }}</p></x-reveal>

                    <x-reveal variant="fade-up" delay="100">
                    <h1 class="mt-6 font-display text-5xl leading-[1.02] tracking-tight text-brand-ink md:text-6xl lg:text-[5.5rem]">
                        {{ __('auth.verify_hero_h1_pre') }}<em class="font-normal italic text-chapter-produce">{{ __('auth.verify_hero_h1_em') }}</em>.
                    </h1>
                    </x-reveal>

                    <x-reveal variant="fade-up" delay="200">
                    <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-ink/60">
                        {{ __('auth.verify_hero_body') }}
                    </p>
                    </x-reveal>

                    {{-- Steps list --}}
                    <x-reveal variant="fade-up" delay="300">
                    <ol class="mt-12 max-w-xl text-sm" aria-label="{{ __('auth.verify_steps_label') }}">
                        <li class="grid grid-cols-[auto_1fr] items-baseline gap-6 border-b border-brand-ink/10 py-5">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">01</span>
                            <div>
                                <p class="font-display text-xl text-brand-ink">{{ __('auth.verify_step_1_title') }}</p>
                                <p class="mt-1 text-brand-ink/65">{{ __('auth.verify_step_1_desc') }}</p>
                            </div>
                        </li>
                        <li class="grid grid-cols-[auto_1fr] items-baseline gap-6 border-b border-brand-ink/10 py-5">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">02</span>
                            <div>
                                <p class="font-display text-xl text-brand-ink">{{ __('auth.verify_step_2_title') }}</p>
                                <p class="mt-1 text-brand-ink/65">{{ __('auth.verify_step_2_desc') }}</p>
                            </div>
                        </li>
                        <li class="grid grid-cols-[auto_1fr] items-baseline gap-6 border-b border-brand-ink/10 py-5">
                            <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">03</span>
                            <div>
                                <p class="font-display text-xl text-brand-ink">{{ __('auth.verify_step_3_title') }}</p>
                                <p class="mt-1 text-brand-ink/65">{{ __('auth.verify_step_3_desc') }}</p>
                            </div>
                        </li>
                    </ol>
                    </x-reveal>
                </div>

                {{-- ── Right: aside ── --}}
                <x-reveal variant="fade" delay="150">
                <div class="self-start bg-brand-paper p-8 lg:p-10" data-testid="verify-email-aside">
                    <p class="eyebrow text-[0.65rem] tracking-widest text-chapter-produce">{{ __('auth.verify_panel_eyebrow') }}</p>
                    <h2 class="mt-3 font-display text-3xl text-brand-ink">{{ __('auth.verify_panel_h3') }}</h2>
                    <p class="mt-4 text-sm leading-relaxed text-brand-ink/60">{{ __('auth.verify_panel_body') }}</p>

                    <div class="mt-8 h-px bg-brand-ink/10"></div>

                    {{-- W2-20: show subscription details when the customer just
                         completed onboarding so they can see what they set up. --}}
                    @if ($subscription !== null)
                        <div class="mt-6 space-y-4 text-sm" data-testid="verify-email-confirmation">
                            <p class="font-mono text-[0.65rem] uppercase tracking-wider text-chapter-produce">{{ __('What we just set up') }}</p>
                            <dl class="space-y-4">
                                <div>
                                    <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('Your box') }}</dt>
                                    <dd class="mt-1 font-display text-lg text-brand-ink">
                                        {{ __(ucfirst($subscription->box_size)) }} ·
                                        {{ $subscription->plan->value === 'weekly' ? __('Every week') : __('Every two weeks') }}
                                        @if ($eggsAddOn) · {{ __('Eggs add-on') }}@endif
                                    </dd>
                                </div>
                                @if ($isPickup)
                                    <div>
                                        <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('Pickup location') }}</dt>
                                        <dd class="mt-1 font-display text-lg text-brand-ink">{{ $pickup['name'] }}</dd>
                                    </div>
                                @elseif ($address !== null)
                                    <div>
                                        <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('Your delivery address') }}</dt>
                                        <dd class="mt-1 font-display text-lg text-brand-ink">{{ $address->street_line_1 }}, {{ $address->postal_code }} {{ $address->city }}</dd>
                                    </div>
                                @endif
                                @if ($firstDelivery !== null)
                                    <div>
                                        <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('First delivery') }}</dt>
                                        <dd class="mt-1 font-display text-lg text-brand-ink">
                                            {{ $firstDelivery->locale(app()->getLocale())->translatedFormat('l, j. F Y') }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                        <div class="mt-8 h-px bg-brand-ink/10"></div>
                    @else
                        {{-- Standard envelope info when no subscription yet (new signup) --}}
                        <dl class="mt-6 space-y-4 text-sm">
                            <div>
                                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('auth.verify_panel_sent_to_label') }}</dt>
                                <dd class="mt-1 font-display text-lg break-all text-brand-ink">{{ $authedUser?->email }}</dd>
                            </div>
                            <div>
                                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('auth.verify_panel_expires_label') }}</dt>
                                <dd class="mt-1 font-display text-lg text-brand-ink">{{ __('auth.verify_panel_expires_value') }}</dd>
                            </div>
                        </dl>
                    @endif

                    {{-- Resent status flash --}}
                    @if (session('status') == 'verification-link-sent')
                        <p class="mt-4 font-mono text-[0.65rem] uppercase tracking-wider text-chapter-produce" role="status">
                            {{ __('auth.verify_resent_status') }}
                        </p>
                    @endif

                    {{-- Actions --}}
                    <div class="mt-8 flex flex-col gap-3">
                        {{-- B3: mid-onboarding return path --}}
                        @if ($hasInflightDraft)
                            <a
                                href="{{ route('app.onboarding.start') }}"
                                class="rounded-sm bg-chapter-produce px-6 py-3 text-center text-sm text-brand-cream hover:bg-chapter-produce/90 transition-colors"
                                data-testid="verify-email-continue-onboarding"
                            >
                                {{ __('Continue setup') }}
                            </a>
                        @endif

                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-sm bg-brand-ink px-6 py-3 text-center text-sm text-brand-cream hover:bg-brand-ink/85 transition-colors"
                                data-testid="verify-email-resend"
                            >
                                {{ __('auth.verify_resend') }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('auth.logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full rounded-sm border border-brand-ink/15 bg-transparent px-6 py-3 text-center text-sm text-brand-ink hover:bg-brand-paper/60 transition-colors"
                                data-testid="verify-email-signout"
                            >
                                {{ __('auth.verify_signout') }}
                            </button>
                        </form>
                    </div>

                    {{-- Already confirmed shortcut --}}
                    <div class="mt-8 border-t border-brand-ink/10 pt-6">
                        <a
                            href="{{ $continueHref }}"
                            class="text-xs text-brand-ink/50 underline underline-offset-4 hover:text-brand-ink transition-colors"
                            data-testid="verify-email-already-confirmed"
                        >
                            {{ __('auth.verify_already_confirmed') }}
                        </a>
                    </div>
                </div>
                </x-reveal>

            </div>
        </div>
    </section>

@endsection

@section('footer')
    <x-customer.editorial-footer-band />
@endsection
