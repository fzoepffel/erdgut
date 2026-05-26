{{-- Full-bleed editorial band. Sits inside the dashboard's max-w-6xl
     wrapper but uses w-screen + left-1/2 -translate-x-1/2 to break out
     to the viewport edges. The trick keeps the band self-contained
     without reaching into the layout. --}}
<section
    {{ $attributes->class([
        'relative left-1/2 w-screen -translate-x-1/2 bg-chapter-produce text-brand-cream',
    ]) }}
    data-testid="customer-editorial-footer-band"
    aria-labelledby="customer-footer-band-statement"
>
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
        <div class="grid gap-8 sm:grid-cols-12 sm:items-end">
            <div class="sm:col-span-7">
                {{-- Onion-mark logomark in cream. We mask the existing logo
                     PNG through CSS so the mark inherits the cream colour
                     without needing a dedicated white asset on disk. --}}
                <span
                    aria-hidden="true"
                    class="block h-10 w-10 bg-brand-cream"
                    style="-webkit-mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain; mask: url('{{ asset('images/logo/erdgut-mark-tight.png') }}') no-repeat center / contain;"
                ></span>
                <p id="customer-footer-band-statement" class="mt-6 max-w-md font-display text-2xl italic leading-snug text-brand-cream sm:text-3xl">
                    {{ __('customer.dashboard.footer_band.statement') }}
                </p>
            </div>

            {{-- Nav mirrors the standard <x-app-footer>'s "Das Buch" links
                 (Über uns / Produzenten / Kontakt) plus a logout action.
                 Translation keys are shared with the standard footer so
                 copy stays consistent and we don't carry parallel translations. --}}
            <nav class="sm:col-span-5 sm:text-right" aria-label="{{ __('customer.dashboard.footer_band.statement') }}">
                <ul class="grid grid-cols-2 gap-x-6 gap-y-2 text-xs font-semibold uppercase tracking-eyebrow text-brand-cream/85 sm:justify-end">
                    <li>
                        <a href="{{ route('public.about') }}" class="uppercase tracking-eyebrow underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-cream/60">
                            {{ __('About') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('public.producers') }}" class="uppercase tracking-eyebrow underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-cream/60">
                            {{ __('Producers') }}
                        </a>
                    </li>
                    <li>
                        <a href="{{ route('public.contact') }}" class="uppercase tracking-eyebrow underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-cream/60">
                            {{ __('Contact') }}
                        </a>
                    </li>
                    <li>
                        <form method="POST" action="{{ route('auth.logout') }}" class="inline">
                            @csrf
                            <button
                                type="submit"
                                class="uppercase tracking-eyebrow underline-offset-4 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-cream/60"
                                data-testid="customer-footer-band-logout"
                            >
                                {{ __('customer.dashboard.footer_band.nav_logout') }}
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
</section>
