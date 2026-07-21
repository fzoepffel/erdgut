{{--
    /dev/components — Erdgut component gallery & brand playbook.

    Dev-only route (see routes/web.php). Renders every editorial component
    in a single scrolling page with usage snippets so anyone onboarding
    onto the codebase can see the palette at a glance.

    This view deliberately uses a plain inline layout (no marketing layout)
    so the gallery isn't wrapped in headers/footers that interfere with
    previewing each component's native context.

    Code snippets are wrapped in Blade's verbatim directive so the
    example x-* tags inside them are not compiled as real components.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">

    <title>Erdgut components — dev gallery</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/erdgut-favicon-32.png') }}">
    @include('partials.head-fonts')

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-brand-cream font-sans text-brand-ink antialiased">
    <div class="min-h-screen" data-testid="dev-components">

        {{-- Page header ------------------------------------------------ --}}
        <header class="border-b border-brand-ink/10 bg-brand-paper">
            <div class="mx-auto max-w-6xl px-6 py-10 sm:px-10">
                <p class="eyebrow text-chapter-produce">Dev · brand playbook</p>
                <h1 class="mt-3 font-display text-4xl font-semibold leading-tight text-brand-ink sm:text-5xl">
                    Erdgut component gallery
                </h1>
                <p class="mt-4 max-w-2xl text-sm leading-relaxed text-brand-ink/70">
                    Every shared Blade component in one place — live examples alongside their usage.
                    Dev-only route; gated to <code class="rounded bg-brand-ink/5 px-1.5 py-0.5 text-[11px]">local</code>
                    env in <code class="rounded bg-brand-ink/5 px-1.5 py-0.5 text-[11px]">routes/web.php</code>.
                </p>

                <nav class="mt-8 flex flex-wrap gap-x-4 gap-y-2 text-xs uppercase tracking-eyebrow text-brand-ink/60">
                    <a class="hover:text-brand-ink" href="#editorial">Editorial</a>
                    <span>·</span>
                    <a class="hover:text-brand-ink" href="#chapters">Chapters</a>
                    <span>·</span>
                    <a class="hover:text-brand-ink" href="#buttons">Buttons</a>
                    <span>·</span>
                    <a class="hover:text-brand-ink" href="#forms">Forms</a>
                    <span>·</span>
                    <a class="hover:text-brand-ink" href="#brand">Brand</a>
                    <span>·</span>
                    <a class="hover:text-brand-ink" href="#surfaces">Surfaces</a>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-16 sm:px-10">

            {{-- ============================================================
                 1. EDITORIAL MARKUP
                 ============================================================ --}}
            <section id="editorial" class="space-y-14">
                <div>
                    <p class="eyebrow text-chapter-meat">Section 01</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-brand-ink">Editorial markup</h2>
                    <p class="mt-2 max-w-xl text-sm text-brand-ink/65">
                        Small typographic atoms that carry the newspaper / catalogue feel.
                    </p>
                </div>

                <x-dev.row name="x-eyebrow" desc="Uppercase label; colored by chapter (produce | meat | fish | eggdairy | bread).">
                    @verbatim<x-eyebrow color="produce">Bald geht's los</x-eyebrow>@endverbatim
                </x-dev.row>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                    <x-eyebrow color="produce">Produce</x-eyebrow>
                    <x-eyebrow color="meat">Meat</x-eyebrow>
                    <x-eyebrow color="fish">Fish</x-eyebrow>
                    <x-eyebrow color="eggdairy">Egg &amp; Dairy</x-eyebrow>
                    <x-eyebrow color="bread">Bread</x-eyebrow>
                </div>

                <x-dev.row name="x-triple-star" desc="Decorative ✱✱✱ divider. Colors: accent (red) · ink · cream.">
                    @verbatim<x-triple-star color="accent" />@endverbatim
                </x-dev.row>
                <div class="flex items-center gap-8">
                    <x-triple-star color="accent" />
                    <x-triple-star color="ink" />
                    <span class="inline-flex items-center rounded bg-brand-ink px-4 py-2">
                        <x-triple-star color="cream" />
                    </span>
                </div>

                <x-dev.row name="x-section-heading" desc="Hero heading with optional eyebrow, lead, and actions slot. Reveal-animated.">
                    @verbatim
<x-section-heading eyebrow="Our story" title="Frisches Gemüse." lead="…">
    <x-slot:actions>
        <x-primary-button>CTA</x-primary-button>
    </x-slot:actions>
</x-section-heading>
                    @endverbatim
                </x-dev.row>
                <x-section-heading
                    eyebrow="Our story"
                    title="Frisches Gemüse aus Berliner Bauernhand."
                    lead="Von der Ernte auf deinen Tisch — ohne Umwege."
                    color="produce"
                    align="left"
                >
                    <x-slot:actions>
                        <x-primary-button>Get started</x-primary-button>
                        <x-ghost-link href="#">Learn more</x-ghost-link>
                    </x-slot:actions>
                </x-section-heading>
            </section>

            {{-- ============================================================
                 2. CHAPTERS
                 ============================================================ --}}
            <section id="chapters" class="mt-24 space-y-14">
                <div>
                    <p class="eyebrow text-chapter-meat">Section 02</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-brand-ink">Chapters</h2>
                    <p class="mt-2 max-w-xl text-sm text-brand-ink/65">
                        Marks, badges, and dividers tied to each market stall.
                    </p>
                </div>

                <x-dev.row name="x-chapter-mark" desc="Illustrated chapter icon (onion, beef, scallop, cheese, wheat). Variants: color | white.">
                    @verbatim<x-chapter-mark chapter="produce" size="md" />@endverbatim
                </x-dev.row>
                <div class="flex flex-wrap items-end gap-8">
                    @foreach (['produce', 'meat', 'fish', 'eggdairy', 'bread'] as $chapter)
                        <figure class="text-center">
                            <x-chapter-mark :chapter="$chapter" size="md" />
                            <figcaption class="mt-2 text-[11px] uppercase tracking-eyebrow text-brand-ink/60">{{ $chapter }}</figcaption>
                        </figure>
                    @endforeach
                </div>
                <div class="mt-4 flex flex-wrap items-end gap-8 rounded bg-brand-ink p-6">
                    @foreach (['produce', 'meat', 'fish', 'eggdairy', 'bread'] as $chapter)
                        <x-chapter-mark :chapter="$chapter" size="sm" variant="white" />
                    @endforeach
                </div>

                <x-dev.row name="x-chapter-badge" desc="Category label with chapter coloring. Sizes: xs | sm (default) | md.">
                    @verbatim<x-chapter-badge category="vegetables" size="sm" />@endverbatim
                </x-dev.row>
                <div class="flex flex-wrap gap-2">
                    @foreach (['vegetables', 'fruit', 'dairy', 'bakery', 'fish', 'meat'] as $cat)
                        <x-chapter-badge :category="$cat" size="sm" />
                    @endforeach
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <x-chapter-badge category="vegetables" size="xs" />
                    <x-chapter-badge category="vegetables" size="sm" />
                    <x-chapter-badge category="vegetables" size="md" />
                </div>

                <x-dev.row name="x-chapter-divider" desc="Full-width section divider with mark, eyebrow, title, subtitle.">
                    @verbatim<x-chapter-divider chapter="produce" eyebrow="Chapter 01" title="Produce" subtitle="…" />@endverbatim
                </x-dev.row>
                <x-chapter-divider
                    chapter="produce"
                    eyebrow="Chapter 01"
                    title="Produce"
                    subtitle="Everything that grows above and below the soil — picked this week."
                />
            </section>

            {{-- ============================================================
                 3. BUTTONS & LINKS
                 ============================================================ --}}
            <section id="buttons" class="mt-24 space-y-14">
                <div>
                    <p class="eyebrow text-chapter-meat">Section 03</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-brand-ink">Buttons &amp; links</h2>
                    <p class="mt-2 max-w-xl text-sm text-brand-ink/65">
                        Interactive call-to-action elements. Use <code class="text-[12px]">x-primary-button</code> sparingly; ghost / solid links are the everyday workhorses.
                    </p>
                </div>

                <x-dev.row name="x-primary-button · x-secondary-button · x-danger-button" desc="Solid, ghost, and destructive button tokens. Render as <button> by default.">
                    @verbatim
<x-primary-button>Confirm</x-primary-button>
<x-secondary-button>Cancel</x-secondary-button>
<x-danger-button>Delete</x-danger-button>
                    @endverbatim
                </x-dev.row>
                <div class="flex flex-wrap items-center gap-3">
                    <x-primary-button>Confirm</x-primary-button>
                    <x-secondary-button>Cancel</x-secondary-button>
                    <x-danger-button>Delete</x-danger-button>
                </div>

                <x-dev.row name="x-ghost-link · x-solid-link" desc="Link versions of the buttons — use when the action navigates (e.g. to a details page).">
                    @verbatim
<x-ghost-link href="/foo">Go</x-ghost-link>
<x-solid-link href="/foo">Go</x-solid-link>
                    @endverbatim
                </x-dev.row>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ghost-link href="#">Ghost link</x-ghost-link>
                    <x-solid-link href="#">Solid link</x-solid-link>
                </div>

                <x-dev.row name="x-nav-link" desc="Active-state aware nav item. Pass :active=&quot;request()->routeIs('foo')&quot;.">
                    @verbatim<x-nav-link href="/dashboard" :active="true">Dashboard</x-nav-link>@endverbatim
                </x-dev.row>
                <nav class="flex flex-wrap gap-x-6 gap-y-2">
                    <x-nav-link href="#" :active="true">Dashboard</x-nav-link>
                    <x-nav-link href="#" :active="false">Subscriptions</x-nav-link>
                    <x-nav-link href="#" :active="false">Payments</x-nav-link>
                </nav>
            </section>

            {{-- ============================================================
                 4. FORMS
                 ============================================================ --}}
            <section id="forms" class="mt-24 space-y-14">
                <div>
                    <p class="eyebrow text-chapter-meat">Section 04</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-brand-ink">Forms</h2>
                    <p class="mt-2 max-w-xl text-sm text-brand-ink/65">
                        Borderless inputs with a bottom-border focus. Pair <code class="text-[12px]">x-input-label</code>
                        + <code class="text-[12px]">x-text-input</code> + <code class="text-[12px]">x-input-error</code>.
                    </p>
                </div>

                <x-dev.row name="x-input-label · x-text-input · x-input-error" desc="The standard form-field triplet.">
                    @verbatim
<x-input-label value="Email" />
<x-text-input type="email" name="email" />
<x-input-error :messages="['Invalid email']" />
                    @endverbatim
                </x-dev.row>
                <div class="max-w-md space-y-4">
                    <div>
                        <x-input-label for="demo-email" :value="__('Email')" />
                        <x-text-input id="demo-email" type="email" name="email" class="mt-1 block w-full" placeholder="you@example.com" />
                    </div>
                    <div>
                        <x-input-label for="demo-error" value="Field with error" />
                        <x-text-input id="demo-error" type="text" name="error" class="mt-1 block w-full" value="oops" />
                        <x-input-error :messages="['Invalid value — try again.']" class="mt-2" />
                    </div>
                </div>

                <x-dev.row name="x-auth-session-status" desc="Inline success flash above auth forms.">
                    @verbatim<x-auth-session-status :status="session('status')" />@endverbatim
                </x-dev.row>
                <x-auth-session-status status="Your password has been reset." class="max-w-md" />
            </section>

            {{-- ============================================================
                 5. BRAND & LOCALE
                 ============================================================ --}}
            <section id="brand" class="mt-24 space-y-14">
                <div>
                    <p class="eyebrow text-chapter-meat">Section 05</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-brand-ink">Brand &amp; locale</h2>
                </div>

                <x-dev.row name="x-application-logo" desc="Logo asset picker. Variants: filled · outline · white (for dark bg).">
                    @verbatim<x-application-logo variant="filled" :withWordmark="true" />@endverbatim
                </x-dev.row>
                <div class="flex flex-wrap items-end gap-8">
                    <figure class="text-center">
                        <x-application-logo variant="filled" :withWordmark="true" class="h-16 w-auto" />
                        <figcaption class="mt-2 text-[11px] uppercase tracking-eyebrow text-brand-ink/60">filled</figcaption>
                    </figure>
                    <figure class="text-center">
                        <x-application-logo variant="outline" :withWordmark="true" class="h-16 w-auto" />
                        <figcaption class="mt-2 text-[11px] uppercase tracking-eyebrow text-brand-ink/60">outline</figcaption>
                    </figure>
                    <figure class="rounded bg-brand-ink p-4 text-center">
                        <x-application-logo variant="white" :withWordmark="true" class="h-16 w-auto" />
                        <figcaption class="mt-2 text-[11px] uppercase tracking-eyebrow text-brand-cream/80">white</figcaption>
                    </figure>
                </div>

                <x-dev.row name="x-flag · x-language-selector" desc="Inline SVG flags (de/en/fr) and the dropdown picker. Variants: default · compact.">
                    @verbatim
<x-flag locale="de" />
<x-language-selector variant="compact" />
                    @endverbatim
                </x-dev.row>
                <div class="flex items-center gap-6">
                    <x-flag locale="de" class="h-5 w-7" />
                    <x-flag locale="en" class="h-5 w-7" />
                    <x-flag locale="fr" class="h-5 w-7" />
                    <span class="mx-2 h-6 w-px bg-brand-ink/20"></span>
                    <x-language-selector variant="compact" />
                </div>

                <x-dev.row name="x-founder-signature" desc="Italic closing signature for letters / emails. Sizes: sm · md · lg. Colors: ink · cream.">
                    @verbatim<x-founder-signature names="Lucille, Arthur & Milan" size="md" />@endverbatim
                </x-dev.row>
                <div class="space-y-3">
                    <x-founder-signature names="Lucille, Arthur & Milan" size="sm" />
                    <x-founder-signature names="Lucille, Arthur & Milan" size="md" />
                    <x-founder-signature names="Lucille, Arthur & Milan" size="lg" />
                </div>
                <div class="mt-3 rounded bg-brand-ink p-4">
                    <x-founder-signature names="Lucille, Arthur & Milan" size="md" color="cream" />
                </div>
            </section>

            {{-- ============================================================
                 6. SURFACES & STATE
                 ============================================================ --}}
            <section id="surfaces" class="mt-24 space-y-14">
                <div>
                    <p class="eyebrow text-chapter-meat">Section 06</p>
                    <h2 class="mt-2 font-display text-3xl font-semibold text-brand-ink">Surfaces &amp; state</h2>
                </div>

                <x-dev.row name="x-empty-state" desc="Placeholder card for empty lists. Variants: card · card-muted · inset. Tones: neutral · amber.">
                    @verbatim<x-empty-state title="Nothing here yet" description="…" />@endverbatim
                </x-dev.row>
                <div class="grid gap-4 md:grid-cols-2">
                    <x-empty-state
                        title="Nothing here yet"
                        description="When you have subscriptions, they'll show up on this page."
                    />
                    <x-empty-state
                        title="Needs your attention"
                        description="A payment failed — update your method to continue."
                        tone="amber"
                    />
                    <x-empty-state
                        title="Muted"
                        description="Secondary placeholder."
                        variant="card-muted"
                    />
                    <x-empty-state
                        title="Inset"
                        description="Inline / inside-a-card placeholder."
                        variant="inset"
                    />
                </div>

                <x-dev.row name="x-status-badge" desc="Enum-coloured status chip. Maps common values to the palette.">
                    @verbatim<x-status-badge value="scheduled" />@endverbatim
                </x-dev.row>
                <div class="flex flex-wrap gap-2">
                    @foreach (['scheduled', 'delivered', 'active', 'paid', 'locked', 'paused', 'pending', 'skipped', 'cancelled', 'failed'] as $s)
                        <x-status-badge :value="$s" />
                    @endforeach
                </div>

                <x-dev.row name="x-month-label" desc="Localized month name. Colors: cream · ink.">
                    @verbatim<x-month-label :month="10" :year="2026" />@endverbatim
                </x-dev.row>
                <div class="flex gap-6">
                    <span class="rounded bg-brand-ink px-3 py-2">
                        <x-month-label :month="(int) now()->month" :year="(int) now()->year" color="cream" />
                    </span>
                    <x-month-label :month="(int) now()->month" :year="(int) now()->year" color="ink" />
                </div>

                <x-dev.row name="x-dropdown" desc="Alpine-driven dropdown. Compose with trigger + content slots.">
                    @verbatim
<x-dropdown>
  <x-slot:trigger>…</x-slot:trigger>
  <x-slot:content>…</x-slot:content>
</x-dropdown>
                    @endverbatim
                </x-dev.row>
                <x-dropdown>
                    <x-slot:trigger>
                        <button type="button" class="inline-flex items-center gap-2 rounded border border-brand-ink/20 bg-brand-paper px-4 py-2 text-sm font-semibold text-brand-ink">
                            Options
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot:trigger>
                    <x-slot:content>
                        <button class="block w-full px-4 py-2 text-left text-sm text-brand-ink hover:bg-brand-ink/5">Edit</button>
                        <button class="block w-full px-4 py-2 text-left text-sm text-brand-ink hover:bg-brand-ink/5">Duplicate</button>
                        <button class="block w-full px-4 py-2 text-left text-sm text-chapter-meat hover:bg-chapter-meat/5">Delete</button>
                    </x-slot:content>
                </x-dropdown>

                <x-dev.row name="x-modal" desc="Named modal dialog with focus trap. Open via $dispatch('open-modal', 'name').">
                    @verbatim
<x-modal name="demo" :show="false">
  <div class="p-6">…</div>
</x-modal>
                    @endverbatim
                </x-dev.row>
                <button
                    type="button"
                    class="btn-ghost"
                    x-data
                    x-on:click="$dispatch('open-modal', 'demo-modal')"
                >Open demo modal</button>

                <x-modal name="demo-modal" :show="false" maxWidth="md">
                    <div class="p-8">
                        <p class="eyebrow text-chapter-produce">Demo modal</p>
                        <h3 class="mt-2 font-display text-2xl font-semibold text-brand-ink">Hello from the modal</h3>
                        <p class="mt-3 text-sm text-brand-ink/70">
                            Triggered via Alpine <code>$dispatch</code>. Close with ESC or by clicking the backdrop.
                        </p>
                        <div class="mt-6 flex justify-end gap-2">
                            <button class="btn-ghost" x-data x-on:click="$dispatch('close-modal', 'demo-modal')">Close</button>
                            <button class="btn-solid" x-data x-on:click="$dispatch('close-modal', 'demo-modal')">OK</button>
                        </div>
                    </div>
                </x-modal>
            </section>

            {{-- Footer ------------------------------------------------ --}}
            <footer class="mt-24 border-t border-brand-ink/10 pt-10 text-sm text-brand-ink/60">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <span>
                        {{ count(glob(resource_path('views/components/*.blade.php'))) }} shared components ·
                        Laravel {{ app()->version() }}
                    </span>
                    <span>
                        Env: <code class="rounded bg-brand-ink/5 px-1.5 py-0.5">{{ app()->environment() }}</code>
                    </span>
                </div>
            </footer>
        </main>
    </div>
</body>
</html>
