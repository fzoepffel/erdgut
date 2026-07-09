{{-- Status page for a restaurant application awaiting admin review. Layout
     adapted from the box-signup verify-email page (hero + steps left, status
     aside right) — minus everything that doesn't apply here: there is no
     mail to resend and nothing the applicant has to do. --}}
<x-shop-layout :title="__('restaurant.pending_title')">
    <section class="relative border-b border-brand-ink/10 py-16 lg:py-24">
        <div class="mx-auto max-w-[1400px] px-6 lg:px-12">
            <div class="grid gap-10 lg:grid-cols-[1.1fr_1fr] lg:items-start lg:gap-16">

                {{-- ── Left: hero + steps ── --}}
                <div>
                    <x-reveal><p class="eyebrow text-chapter-produce">{{ __('restaurant.nav_public') }}</p></x-reveal>

                    <x-reveal variant="fade-up" delay="100">
                        <h1 class="mt-6 font-display text-5xl leading-[1.02] tracking-tight text-brand-ink md:text-6xl lg:text-[5.5rem]">
                            {{ __('restaurant.pending_hero_pre') }}<em class="font-normal italic text-chapter-produce">{{ __('restaurant.pending_hero_em') }}</em>.
                        </h1>
                    </x-reveal>

                    <x-reveal variant="fade-up" delay="200">
                        <p class="mt-8 max-w-xl text-base leading-relaxed text-brand-ink/60">
                            {{ __('restaurant.pending_hero_body') }}
                        </p>
                    </x-reveal>

                    {{-- Steps: where the application stands --}}
                    <x-reveal variant="fade-up" delay="300">
                        <ol class="mt-12 max-w-xl text-sm" aria-label="{{ __('restaurant.pending_steps_label') }}">
                            <li class="grid grid-cols-[auto_1fr] items-baseline gap-6 border-b border-brand-ink/10 py-5">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">01</span>
                                <div>
                                    <p class="font-display text-xl text-brand-ink">{{ __('restaurant.pending_step_1_title') }}</p>
                                    <p class="mt-1 text-brand-ink/65">{{ __('restaurant.pending_step_1_desc') }}</p>
                                </div>
                            </li>
                            <li class="grid grid-cols-[auto_1fr] items-baseline gap-6 border-b border-brand-ink/10 py-5">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">02</span>
                                <div>
                                    <p class="font-display text-xl text-brand-ink">{{ __('restaurant.pending_step_2_title') }}</p>
                                    <p class="mt-1 text-brand-ink/65">{{ __('restaurant.pending_step_2_desc') }}</p>
                                </div>
                            </li>
                            <li class="grid grid-cols-[auto_1fr] items-baseline gap-6 border-b border-brand-ink/10 py-5">
                                <span class="font-mono text-[0.65rem] uppercase tracking-widest text-chapter-produce" aria-hidden="true">03</span>
                                <div>
                                    <p class="font-display text-xl text-brand-ink">{{ __('restaurant.pending_step_3_title') }}</p>
                                    <p class="mt-1 text-brand-ink/65">{{ __('restaurant.pending_step_3_desc') }}</p>
                                </div>
                            </li>
                        </ol>
                    </x-reveal>
                </div>

                {{-- ── Right: application status aside ── --}}
                <x-reveal variant="fade" delay="150">
                    <div class="self-start bg-brand-paper p-8 lg:p-10" data-testid="restaurant-pending-aside">
                        <p class="eyebrow text-[0.65rem] tracking-widest text-chapter-produce">{{ __('restaurant.pending_panel_eyebrow') }}</p>
                        <h2 class="mt-3 font-display text-3xl text-brand-ink">{{ __('restaurant.pending_panel_title') }}</h2>
                        <p class="mt-4 text-sm leading-relaxed text-brand-ink/60">{{ __('restaurant.pending_panel_body') }}</p>

                        <div class="mt-8 h-px bg-brand-ink/10"></div>

                        <dl class="mt-6 space-y-4 text-sm">
                            @if ($profile?->business_name)
                                <div>
                                    <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('restaurant.field_business_name') }}</dt>
                                    <dd class="mt-1 font-display text-lg text-brand-ink">{{ $profile->business_name }}</dd>
                                </div>
                            @endif
                            <div>
                                <dt class="font-mono text-[0.65rem] uppercase tracking-wider text-brand-ink/45">{{ __('restaurant.pending_panel_reply_to_label') }}</dt>
                                <dd class="mt-1 break-all font-display text-lg text-brand-ink">{{ auth()->user()?->email }}</dd>
                            </div>
                        </dl>

                        <div class="mt-8 flex flex-col gap-3">
                            <form method="POST" action="{{ route('auth.logout') }}">
                                @csrf
                                <button type="submit"
                                        class="w-full rounded-sm border border-brand-ink/15 bg-transparent px-6 py-3 text-center text-sm text-brand-ink transition-colors hover:bg-brand-cream/60"
                                        data-testid="restaurant-pending-signout">
                                    {{ __('restaurant.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </x-reveal>

            </div>
        </div>
    </section>
</x-shop-layout>
