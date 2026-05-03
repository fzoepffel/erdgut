# Walkthrough 2 — Design alignment + signup/box-setup audit

This is a live findings doc for the second walkthrough (2026-04-28), focused on:
1. **Design alignment** — the customer wants the marketing-homepage's voice, header, and atmosphere carried through onboarding, dashboard, and box pages.
2. **Data integrity, empty states, and pricing** — verify a non-medium box with eggs add-on prices correctly end-to-end.

Findings are appended as we encounter them in the browser walkthrough. Implementation order is prioritized at the bottom and will be turned into PR-sized tickets once the walkthrough is complete.

> **Status as of 2026-05-03:** 35 of 38 findings ✓ shipped. Remaining open:
> - **W2-12** — Box-size cards visual treatment (PR #55 attempted, rolled back in #56)
> - **W2-05** — decided no-op (keep current wizard order)
> - **W2-06 / W2-09 / W2-29** — superseded or non-bugs, kept for context
>
> Each `### W2-XX` heading below carries its own status marker. The implementation priority table at the bottom maps groups of findings to PRs.

---

## Dev-environment fixes (already in working tree)

These don't ship as customer-visible changes but are needed for the walkthrough to be possible at all. Will be committed alongside the first PR coming out of this work.

### ~~W2-DEV-01~~ ✓ shipped · PHP 8.5 deprecation notices leak into rendered HTML

**Symptom:** Every dev-mode page response had multi-paragraph `Deprecated: Constant PDO::MYSQL_ATTR_SSL_CA is deprecated since 8.5` notices prepended to the HTML, dominating the viewport before any styled content.

**Cause:** Laravel framework's `vendor/laravel/framework/config/database.php` references `PDO::MYSQL_ATTR_SSL_CA`, renamed to `Pdo\Mysql::ATTR_SSL_CA` in PHP 8.5. `php artisan serve` spawns `php -S` as a child which doesn't inherit `-d display_errors=0` from the parent and ignores `public/.user.ini`.

**Fix in tree:** Suppress at the top of `bootstrap/app.php` only when `APP_ENV=local`. Drop reference to `E_STRICT` (removed in PHP 8.4 and would itself raise a deprecation).

### ~~W2-DEV-02~~ ✓ shipped (`<x-csp>` Blade component) · Vite dev assets blocked by hardcoded CSP

**Symptom:** With `php artisan serve` + `npm run dev`, the page comes up unstyled. Chrome's CSP blocks `<link rel=stylesheet href="http://127.0.0.1:5173/...">` because the meta-tag CSP in each layout sets `style-src 'self' 'unsafe-inline' https://fonts.bunny.net` — no origin for the Vite dev server.

**Workaround used:** Run `npm run build` to compile to `public/build/` so assets are served from `'self'`.

**Real fix (for a later PR):** In each layout that hardcodes CSP via meta tag, conditionally add the Vite dev origin (`http://127.0.0.1:5173 ws://127.0.0.1:5173`) to `script-src`/`style-src`/`connect-src` when `app()->environment('local')`. Or, better, move CSP out of meta tags and into the existing `SecurityHeaders` middleware where it can be composed centrally.

---

## Customer-facing findings

### ~~W2-01~~ ✓ shipped (PR #48 — `<x-customer-header>` 3 variants) · Three different headers across customer surfaces — *the headline issue*

| Surface | Layout | Header treatment |
|---|---|---|
| Marketing / homepage | `layouts/marketing.blade.php` | Olive-green sticky masthead `#709452` w/ dark-green `#21341d` masked onion + wordmark, top-level nav (Home/About/Producers/Contact), language selector + Sign-up button |
| Onboarding wizard | `layouts/onboarding.blade.php` | Cream paper background, small `<x-application-logo>` mark + a *separate text wordmark* "Erdgut" + an editorial eyebrow + language selector + login link |
| Logged-in app | `layouts/app.blade.php` + `layouts/navigation.blade.php` | Cream/95 backdrop-blur, full coloured `erdgut.png` logo + tab nav + user dropdown |

**Customer experience:** Click "ABONNIEREN" on the green-masthead homepage → next page is a different brand. Visual continuity is broken on the *very first click after the homepage*. Same on `/onboard/address` and after signup on `/dashboard`.

**Resolution direction:** Adopt the marketing layout's olive-green sticky masthead in `layouts/onboarding.blade.php` and `layouts/app.blade.php`. Same masked logo, same colour, same density. The internal nav (Dashboard / Subscription / Deliveries / Products) lives inside that masthead instead of a separate cream nav.

### ~~W2-02~~ ✓ shipped (PR #48) · Three different logo treatments

- Homepage: `erdgut-mark-tight.png` + `erdgut-wordmark-tight.png` masked in `#21341d` on `#709452`. Strong, distinctive.
- Onboarding: `<x-application-logo>` (the small monochrome mark) + a `<span>` text wordmark "Erdgut" rendered in body font.
- App: full coloured `erdgut.png` (different proportions from homepage marks).

**Resolution direction:** One canonical "branded header" partial that embeds the masked-logo treatment, used everywhere. Drop the text wordmark from the onboarding layout.

### ~~W2-03~~ ✓ shipped (PR #54 — wizard trimmed to 4 visible segments) · Marketing promises 4 wizard steps, reality is 6

The homepage's "DER RHYTHMUS · Von der Anmeldung zur Haustür in vier Schritten" lists `01 Konto / 02 Tarif / 03 Wir stellen zusammen / 04 Du behältst Kontrolle`. The actual wizard has **six visible steps**: `01 KONTO · 02 ADRESSE · 03 VORLIEBEN · 04 MODELL · 05 ZEITPLAN · 06 ZAHLUNG`.

**Resolution direction:** Either trim the wizard to 4 visible top-level steps (collapse Adresse into Vorlieben? collapse Zeitplan into Zahlung?) or update the homepage copy to match reality. Probably the first — fewer perceived steps is a real conversion lever.

### ~~W2-04~~ ✓ shipped (PR #54 — MODELL dropped from subscription flow) · MODELL (intent) step is redundant in the subscription flow

When a visitor clicks "ABONNIEREN" on the homepage, the intent (subscription) is stored on the draft and the customer is taken into the wizard. But the step indicator still shows `04 MODELL` — the trial-vs-subscription decision the customer just made on the homepage one click earlier.

**User decision (recorded during walkthrough):**
- Subscription flow: **drop MODELL entirely** — it's redundant.
- Trial flow: **keep MODELL** as a confirmation/upsell step ("Sicher, dass du nur die Testbox willst und nicht direkt das Abo?") but render it as a *non-progress* interstitial — don't show it as a numbered step in the top bar; show it as a single-screen check-in. The user shouldn't feel they're going backwards through a numbered list when this fires.

### W2-05 — ⏸ decided no-op (per "Open questions resolved": keep current order) · Wizard begins with account creation, before customer sees what they're buying

Step 1 asks for `Vollständiger Name / E-Mail / Passwort / Passwort bestätigen / Telefon`. The customer has not yet been shown box sizes, prices, or the schedule. They have to hand over a password before they understand what they're signing up for.

**Resolution direction:** Reorder the wizard so the customer picks size + sees price first (current step 03 VORLIEBEN), and account creation comes later — ideally just before payment. This is the standard e-commerce checkout pattern. It also lets us drop the "MODELL" upsell into the same step as "size pick" since both are about *what you're buying*.

### W2-06 · ~~"DEINE BOX" sidebar feels like a dangling placeholder~~ → superseded by W2-17

The original ask was "hide the sidebar until something is in it". User's preference is stronger: turn the sidebar into a running summary of *every* gathered piece of info. See W2-17.

### ~~W2-07~~ ✓ shipped (PR #48 — wizard-variant header is auth-aware) · Top-right "Anmelden" link still showing after account is created

After submitting the Konto step, a `User` row is created (verified via tinker: `id=4 verified=no`). But the wizard layout's top right still shows the "Anmelden" link, suggesting the user isn't authenticated yet — or the layout doesn't reflect auth state.

**Open question:** Is the User created but not logged in until they finish the wizard? Or are they logged in but the layout uses `@guest`-style logic that doesn't fire here? Need to check the controller behaviour. Either way: after Konto submit, the top-right link should change to "Mein Konto" / "Abmelden" (already-authenticated UX) rather than "Anmelden" which suggests they should sign in again.

### ~~W2-08~~ ✓ shipped (PR #54 — 4-segment compact step indicator) · Step indicator wraps to two lines and reads cluttered

The `01 KONTO · 02 ADRESSE · 03 VORLIEBEN · 04 MODELL · 05 ZEITPLAN · 06 ZAHLUNG` row wraps after step 5 at desktop width. Visual density is high; the dot separators don't carry meaning.

**Resolution direction:** Either trim to fewer steps (W2-03), or collapse to a compact progress bar (filled segments) instead of a labeled-step list. Or both.

---

### W2-09 · ~~BUG: session lost between address submit and preferences step~~ → preview-browser cookie quirk

**Updated finding (after curl repro):** Reproducing the same flow via curl works fine end-to-end — login persists, address POST returns 302 to /onboard/preferences, session survives. The "bouncing to `/` after every form POST" we saw in the Claude Preview browser is the **same preview-browser cookie quirk noted in `customer-ux-walkthrough-followups.md`** ("Form-submit logout bug"). Not a production bug.

**Action:** Don't ship a fix here. But:
- Add to docs/customer-ux-walkthrough-followups.md as a confirmed dev-environment foot-gun.
- Future walkthroughs in the preview browser should use curl/direct-navigation between steps instead of relying on form-submit redirect chains.
- Revisit if this ever reproduces in real Chrome.

### ~~W2-10~~ ✓ shipped (PR #48 — auth variant of `<x-customer-header>`) · A *fourth* layout — `/auth/login` uses `layouts/guest.blade.php` (or similar)

In addition to marketing / onboarding / app, the login page has its own layout: faded white-on-cream wordmark centered above the form, language selector top right, no nav at all. **Four different brand expressions for one product.**

**Resolution direction:** Same as W2-01 — adopt the marketing masthead everywhere customer-facing. The login page especially should feel like an entry to the same store, not a separate utility page.

---

### ~~W2-11~~ ✓ shipped — copy now reads "EUR :amount / Lieferung" · "Wert EUR 20" label on box-size cards is ambiguous

The Vorlieben step renders each size card as `Klein / ~6 Produkte (Wert EUR 20)`. "Wert" reads like *content/retail value of items inside*, not "what you pay". Config name (`included_value_by_size`) actually represents the recurring delivery price floor.

**Resolution direction:** Render as `EUR 20 pro Lieferung` or `ab EUR 20 / Lieferung` — same number, unambiguous meaning. Could even drop "~6 Produkte" if it's a misleading proxy and the customer cares about the price line item.

### W2-12 — ⏳ open — visual direction tried + rolled back (PR #55 → #56); needs another pass · Box-size cards are text-only — design opportunity

After the homepage's chapter-coloured chapter strip and the customer box show's stall imagery, the box-size pick is the page where atmosphere drops out hardest. The cards are pure text in a thin border. A subtle box silhouette per size, or a chapter-produce wash, would carry the brand voice through.

### ~~W2-13~~ ✓ shipped (PR #47 — `<x-onboarding.summary>`) · BUG: right-sidebar summary doesn't update as the customer fills the form

Selected Klein + Eier in the form (both visually highlighted). Right sidebar still shows `Ziel ... Abo` with no price. The sidebar is server-rendered from the persisted draft, so it can't reflect in-progress choices. Customer never sees a running total while choosing.

**Resolution direction:** Make the sidebar Alpine-reactive: bind it to the form's current values. When customer toggles size or extras, recompute the price preview client-side. On submit, server validates and persists; if values diverge, server response wins. Same pattern as the box budget panel uses on `boxes/show` (`bindEstimate` prop already exists).

### ~~W2-14~~ ✓ shipped (PR #54 — controller pre-selects first valid weekday + timeslot) · Schedule step's "Keine Präferenz" default is a validation trap

The Zeitplan step (`/onboard/subscription/schedule`) shows two dropdowns:
- *Bevorzugter Wochentag* — default option `Keine Präferenz` (empty value), then Montag…Freitag
- *Bevorzugtes Zeitfenster* — default option `Keine Präferenz` (empty value), then Morgen/Nachmittag/Abend

But the FormRequest (`SubscriptionScheduleStepRequest`) marks both as `required_if:fulfillment_mode,delivery`. So the visible default is *not actually selectable* — submitting with "Keine Präferenz" fails validation.

**Resolution direction:** Either:
- Drop the "Keine Präferenz" placeholder, default-select the first valid option (e.g. Wednesday + Afternoon), and let the customer change it. **Cleaner.**
- Or change validation to allow null (server fills in defaults later).

### ~~W2-15~~ ✓ shipped (BoxPriceCalculator drives commitment-step copy) · Submit on commitment confirmation actually advances — but the cards show 35 EUR for trial and 20 EUR for subscription, not size-aware

Trial card: `35 EUR` (config `trial_price_oneoff_ttc`).
Sub card: "Erste Box EUR 35, danach ab EUR 20 pro Lieferung".

Both numbers are hardcoded against `small`. If the customer chose Klein (small) on the previous step that's accurate. If they chose Mittel/Groß (35/42 EUR recurring), the sub card still says "from EUR 20 per delivery" — wrong floor.

**Resolution direction:** Pass the customer's already-chosen `box_size` into commitment view; render `from EUR :recurring per delivery` using `included_value_by_size[$size]`.

### ~~W2-16~~ ✓ shipped (PRs #43–#45 → `App\Services\Billing\BoxPriceCalculator`) · 🚨 BUG + DECISION: pricing model needs to be reset, then a single calculator service used everywhere

**Decision recorded during walkthrough:** There is no business rule that the first box of a subscription costs anything different from the following boxes. Every subscription delivery costs the same: `size-base + eggs-addon`.

The current code has a leftover concept of a "trial-flat first charge":
- `config('box.trial_price_ttc')` = 35 EUR
- `Subscription::isTrialBillingPending()` true until first successful recurring charge
- `BoxPricing::subscriptionRecurringTotal()` returns the trial-flat price (35) instead of the size-based price for the first charge
- `Subscription::trial_completed_at` flips this to false after the first charge

This whole "first box is special" branch is wrong as a business model. Remove it.

**The customer-visible bug we found** (Klein + Eier walkthrough):
- Page says: ERSTE BOX 35,00 EUR / FOLGELIEFERUNGEN ab 20,00 EUR
- BoxPricing currently calculates: ERSTE BOX 39,50 / FOLGELIEFERUNGEN 24,50 (with the trial-flat branch)
- **What it should be after the fix:** every charge 24,50 EUR

The display in `resources/views/onboarding/subscription/payment.blade.php` recomputes prices from raw config and skips the eggs add-on entirely — even before the trial-flat issue, the displayed numbers don't match `BoxPricing::subscriptionRecurringTotal()`. Two layers of wrong.

**Implementation (this is one PR, three pieces):**

1. **New service: `App\Services\Billing\BoxPriceCalculator`** (or fold into existing `BoxPricing`).
   Single source of truth, callable from any surface:
   ```php
   $calc = app(BoxPriceCalculator::class);
   $calc->subscriptionPerDelivery($size, $eggsAddOn): float        // e.g. 24.50
   $calc->subscriptionLineItems($size, $eggsAddOn): array          // for itemized display
   $calc->trialOneOff($size = null, $eggsAddOn): float             // for /trial flow
   ```
   Test it directly. Every other call site delegates to it.

2. **Drop the trial-flat-first-charge branch.**
   - `BoxPricing::subscriptionRecurringTotal()`: stop branching on `isTrialBillingPending`.
   - `Subscription::trial_completed_at`: keep the column (back-compat) but stop reading it for pricing. Mark deprecated in a comment.
   - `config('box.trial_price_ttc')`: only used for the **trial-box one-off** flow now (which has its own price), not for subscription's first charge. Leave the config but stop using it from subscription paths.
   - `ChargeSubscriptions` command + admin manual charge + onboarding setup checkout — all read through the new calculator.

3. **Replace the payment-summary view (and every other pricing surface) with calls to the calculator.**
   The Zahlung page becomes:
   ```
   TARIF                        Jede Woche
   BOX-GRÖSSE                   Klein
   EIER ZUSATZ                  + EUR 4,50 / Lieferung
   GESAMT PRO LIEFERUNG         EUR 24,50
   ```
   No "first box" / "following deliveries" split — every box the same number. If the customer changes mind on size or eggs in a later step, this number recomputes from the same calculator.

**Other surfaces that need to read from the same calculator:**
- Onboarding `/preferences` size cards (W2-11: "Wert EUR 20" → "EUR 20 / Lieferung")
- Onboarding `/commitment` subscription card subtitle (W2-15: hardcoded `from EUR 20` even for non-Klein)
- Onboarding `/payment` summary (this finding)
- Customer dashboard subscription panel (currently shows status only; should show recurring price)
- Customer `/subscriptions/show` (current edit page)
- Admin subscription index/detail
- Email templates (delivery reminder, charge confirmation)

**Acceptance:** Every surface that shows a customer a price shows the same number for the same `(size, eggs)` choice. Tested by a single feature test that walks the wizard at every (size × eggs) combo and asserts the displayed total at /payment matches the actual SumUp checkout amount + matches the dashboard later.

### ~~W2-17~~ ✓ shipped (PR #47 — `<x-onboarding.summary>`) · Right-sidebar `DEINE BOX` should show *everything* gathered, not only box

**Decision recorded:** the right-rail summary on the wizard should be the customer's running shopping cart of *every* piece of information they've handed over so far — not just the box size.

Today the rail shows: `DEINE BOX · Größe · Ziel · Extras`. After the customer has filled Konto + Adresse + Vorlieben + Modell + Zeitplan, the rail should show all of:

```
DEIN KONTO
Anna Schmitz · anna.schmitz@example.test

DEINE LIEFERADRESSE
Kastanienallee 12, 10435 Berlin

DEINE BOX
Klein · 6 Produkte · EUR 24,50 / Lieferung
Eier-Zusatz aktiv
Allergien: Gluten, Nüsse

DEIN ZEITPLAN
Wöchentlich · Mittwoch nachmittags · Lieferung
```

Each block appears progressively as the corresponding step is completed. Each block has a subtle "edit" link back to the relevant step.

This:
- Reassures the customer that what they entered isn't lost.
- Replaces the empty-placeholder W2-06 finding with something useful.
- Gives the customer a self-review surface before they hand over a card on the final step.
- Naturally surfaces the price computed by the new calculator (W2-16).

**Implementation note:** Build this as a new `<x-onboarding.summary>` Blade component. Driven by the `OnboardingDraft` data + the persisted User/Address/Subscription if they exist. Extends as new steps are added later. Same component used at the bottom of the payment step as a final confirmation card.

### ~~W2-18~~ ✓ shipped (PR #48 — clean header eyebrow handling) · Header `step` flag inherits "commitment" semantics — eyebrow shows on a step that's neither first nor visually distinctive

PR #23 conditionally shows the "EIN PLATZ AM TISCH" eyebrow only when `$step === 'commitment'`. That assumption made sense when commitment was step 1, but in the current flow it's step 4 (after Konto, Adresse, Vorlieben). So the eyebrow flickers in/out across the wizard for no apparent reason. Cleared up automatically once W2-01 (unified header) lands.

### ~~W2-20~~ ✓ shipped (PRs #46 + #48 + #49 — verify-email continuity) · Email-verification gate offers no continuity from the wizard

**What happened:** Customer completes payment-setup step. Browser redirects to `/app/dashboard`. The `verified` middleware bounces them to `/auth/email/verify` ("Fast geschafft. Bestätige deine E-Mail.").

**The page does its job** but offers zero acknowledgement of what just happened: the customer signed up, picked size, picked schedule, handed over a card. The reassurance "Dein Abo ist eingerichtet. Erste Lieferung am Mittwoch, 6. Mai." is missing. Customer leaves the wizard wondering if anything got saved.

**Resolution direction:**
- Add a confirmation block above the verify-email card showing the same `<x-onboarding.summary>` from W2-17: confirmed account, address, box, schedule, price.
- Add a date-of-first-delivery line: "Erste Lieferung: Mittwoch, 6. Mai · 12–16 Uhr".
- Make the "Bestätige deine E-Mail" feel like the *last optional step before food shows up*, not a roadblock.

### ~~W2-21~~ ✓ shipped (PR #48 — verify-email uses `<x-customer-header variant="auth">`) · A *fifth* customer-visible layout (verify-email page)

In addition to:
- marketing — olive-green masthead
- onboarding — cream + small wordmark + step bar
- app (logged-in) — cream + nav tabs
- guest/login — cream + faded centered logo

We now also have a verify-email layout: cream + faded white-on-cream centered logo + minimal footer (Startseite/Über uns/Kontakt only). **Five visual treatments for one product.** Confirms W2-01's diagnosis: the design system needs one canonical customer-facing chrome (header + footer + treatment) used everywhere.

### ~~W2-22~~ ✓ shipped (PR #46 — `Subscription::firstExpectedDeliveryDate()` + dashboard reads from real Delivery) · 🚨 BUG: Dashboard "next delivery" shows a date in the past + ignores customer's chosen weekday

**Reproduced live.** Test customer: signed up Tuesday 2026-04-28 with `delivery_weekday = 3` (Wednesday).

Dashboard top-row shows `NÄCHSTE LIEFERUNG · Mo, 27. Apr` — Monday of the *current* week. That is **yesterday**. And it's a Monday, not the Wednesday the customer chose.

**Cause** (dashboard.blade.php elseif branch):
```php
$nextMonday = \Carbon\CarbonImmutable::parse(
    $primarySubscription->next_charge_date ?? now()
)->startOfWeek();
```

When `next_charge_date` is null (which it is for a brand-new subscription before the first composition runs), the code falls back to `now()->startOfWeek()`. Carbon defaults `startOfWeek()` to Monday — which today is yesterday.

**On top of that**, the calculation:
- Hardcodes Monday regardless of the customer's `delivery_weekday`
- Doesn't account for the box-composition cycle ("Wir stellen jede Box donnerstags für die folgende Montagslieferung" — meaning the *real* first delivery is the Monday/Wednesday/etc *after* the next composition Thursday)
- Falls into the past when run after Monday in any week before the first scheduled delivery row exists

**Resolution direction:**
1. Stop showing a fabricated date when the subscription has no `next_charge_date` and no `Delivery` row yet. Show "Wir bestätigen deinen Liefertermin nach der nächsten Komposition (Donnerstag)" or similar — be honest about the unknown.
2. When a date *is* known, compute it from the customer's `delivery_weekday` + the next-composition-cycle, not from `startOfWeek()`.
3. Once the `DeliveryProjector` has run for the customer's first box, the actual `Delivery` row's `scheduled_for_date` is the source of truth — display that. (PR #5 already wired this; just needs the brand-new pre-composition state to read empty correctly.)

This bug affects every brand-new subscriber. The first thing they see after signing up is a wrong date.

### ~~W2-23~~ ✓ shipped (BoxPriceCalculator surfaced on dashboard subscription panel) · Dashboard shows no price anywhere

After signing up for `Klein + Eier · weekly` the customer's dashboard never shows the recurring price (`24,50 EUR / Lieferung` post-fix). They have to drill into "Dein Abo, Woche für Woche" to find it.

**Resolution direction:** Add a price line to the ABO panel — `Aktiv · EUR 24,50 / Lieferung`. Falls naturally out of the new `BoxPriceCalculator` from W2-16.

### ~~W2-24~~ ✓ shipped (PRs #46 + #49 — new-subscriber hero) · Dashboard for a brand-new subscriber is six mostly-empty cards

Confirmed live: top row (Nächste Lieferung / Deine Box / Abo) plus bottom row (Lieferungen / Box der aktuellen Woche / Almanach) — six panels, of which only "Abo · Aktiv" has anything to say. Every other panel reads as "wird zusammengestellt sobald…".

This is the same dashboard-architecture issue we instrumented (PR #27). Two-week click data will tell us which panels matter, but the brand-new-subscriber state itself is the *worst* state of the dashboard and worth a dedicated layout: a single hero acknowledging the signup, restating the box (size + price + first delivery date), and pointing at the one thing worth doing right now (skip a week / change address / check box on Friday).

### ~~W2-25~~ ✓ shipped (replaced w/ "what was set up" via `<x-onboarding.summary>`) · `Lieferpräferenzen anpassen →` is the only dashboard CTA on the new-subscriber page — and it's an ambiguous action

The new subscriber's dashboard's only call-to-action is "LIEFERPRÄFERENZEN ANPASSEN" (adjust delivery preferences). That's strange — they just set those 30 seconds ago. A new subscriber doesn't yet need to "adjust" anything; they need to *understand what's been set up*.

**Resolution direction:** For a brand-new subscriber, replace this CTA with "WAS WURDE EINGERICHTET" linking to the same summary block (W2-17's `<x-onboarding.summary>` reused). Once they have at least one delivery-row, switch back to the management CTAs.

### ~~W2-26~~ ✓ shipped (PR #48 — unified header used in app layout) · Header on the dashboard is a *fourth* layout (`<x-app-layout>`'s navigation)

Cream/95 backdrop-blur, full coloured `erdgut.png` logo, tabs for Dashboard / Abo / Lieferungen / Produkte, language selector, user dropdown. Different from marketing (olive-green masthead), different from onboarding (cream + small wordmark + step bar), different from login (cream + faded centered logo), different from verify-email (cream + faded centered logo + minimal footer).

Captured for completeness; resolution direction is the same as W2-01.

### ~~W2-27~~ ✓ shipped (`CleanupOrphanUsers` Artisan command) · Orphaned User + Address rows when wizard is abandoned mid-flow

**Confirmed via tinker during the walkthrough.** When a visitor completes the Konto step (`User::create` runs, `Auth::login`, verification email queued) and then abandons before completing payment, the User row sticks around forever. Same with Address (created on the address step). The `OnboardingDraft` is pruned by `PruneOnboardingDrafts` after expiry, but the User/Address aren't.

This is both a privacy concern (we're holding email + phone of someone who never finished signup) and a data-integrity one (the orphan email blocks the customer if they come back later and try to re-register with the same address).

**Resolution direction:** Daily cleanup command — `users:purge-abandoned-onboarding` that deletes Users which:
- have `email_verified_at = null`
- have no Subscription, no Order, no Payment
- have `created_at` older than N days (e.g. 14)

Cascades to Address via existing FK. Add to scheduler.

### ~~W2-28~~ ✓ shipped (PR #49 — payment step uses `<x-onboarding.summary>` as review) · No final "review your order" page before card collection

The current flow goes `commitment → schedule → payment` directly. The customer commits to a card without ever seeing a "here's everything you've chosen, confirm?" gate.

**Resolution direction:** Either:
- Insert a tiny review step between schedule and payment (one screen with the full summary + "Bezahlmethode hinzufügen" CTA).
- OR make the W2-17 sidebar so prominent on the payment page that it acts as the review.

Probably the latter — fewer screens, but the summary needs to be visually equivalent to a review step (full-width on mobile, prominent on desktop).

### W2-29 · ~~Trial-flat first-box advantage~~ → no longer relevant

(Was W2-19. The user clarified: there is no first-box discount — every subscription delivery costs the same. Folded into W2-16's pricing reset.)

### ~~W2-30~~ ✓ shipped (`Subscription::firstExpectedDeliveryDate` consumed on dashboard) · "Mo, 27. Apr" date display: also a problem of *what to show before the box exists*

Tied to W2-22 but the deeper question: when a customer signs up on Tuesday and the next box-composition cycle is Thursday, the *actual* delivery is whichever weekday they chose in the week *after* Thursday's composition. So:
- Tuesday signup, customer picks Wednesday delivery → first delivery is Wednesday of *next* week (May 6)
- Tuesday signup, customer picks Friday delivery → first delivery is Friday of *next* week (May 8)

The dashboard should be able to show this exact date pre-emptively (from the customer's `delivery_weekday` + the next-composition Thursday). Doesn't have to wait for the `Delivery` row to be projected.

**Resolution direction:** Add `Subscription::firstExpectedDeliveryDate(): ?Carbon` that does this calculation. Use it on the dashboard while no `Delivery` row exists yet.

---

## Surfaces still unwalked (placeholder for future passes)

We've walked: marketing homepage `/home`, full subscription onboarding wizard, customer dashboard. We have *not* walked:

- **Trial-box flow** (commitment → trial/delivery → trial/payment → trial/complete). Trial pricing is its own thing (`trial_price_oneoff_ttc` = 35 fixed regardless of size). Commitment-step "MODELL" upsell behaviour (W2-04) needs to be tested in the trial path specifically.
- ~~**Marketing pages** other than `/home` (`/about`, `/producers`, `/contact`)~~ ✓ shipped — verified 2026-05-03: `marketing/about.blade.php` (286 lines, 5 chapters: founder letter, how-a-box-is-assembled, principles, producers invitation, FAQ), `marketing/producers.blade.php` (216 lines, DB-backed cards w/ filter + CTA), `marketing/contact.blade.php` (184 lines, branded form). All on the marketing layout with chapter-coloured editorial sections. (ERD-170/171/172 marked Done.)
- ~~**Login + verify-email flow** branded layout~~ ✓ partially shipped — `auth/login.blade.php`, `auth/register.blade.php`, `auth/forgot-password.blade.php` all carry chapter-produce eyebrows + `font-display` headings on `<x-customer-header variant="auth" />`. (ERD-173 marked Done for the layout work.) End-to-end flow as a real returning user (forgot-password → reset → resend-verification) still unwalked.
- **Customer subscription edit page** `/app/subscriptions/show` — the page we link to from the dashboard. Skip / pause / cancel UX, plus the price display would be a third surface using `BoxPriceCalculator`.
- **Customer payments history** `/app/payments/history`.
- **Customer profile / account settings** `/account/profile/edit`.
- **Admin side** — box setup, weekly catalog, lock & deliver cycle, customer impersonation if any. Critical for verifying the customer-side dashboard stays accurate as admin operations happen.
- **Email rendering** — the verification email + future delivery-reminder + charge-confirmation. Layout consistency across emails (a 6th brand expression?).
- **Mobile viewport pass** — we did everything at desktop width. Onboarding wizard sidebar collapses on mobile?
- **Real SumUp end-to-end** with a sandbox card. Token storage, first charge, refund. We mocked the token in this walkthrough.

---

## Admin-side findings (continued from same walkthrough, 2026-04-28)

Logged in as `admin.test@example.com` (Anna Admin), starting from `/app/admin`. The admin layout uses a left sidebar with `BETRIEB`-grouped navigation — visually distinct from customer-facing chrome, **which is fine and intentional.** The "five different layouts" finding (W2-01) targets *customer-facing* surfaces only; admin is allowed to look like an admin.

### ~~W2-31~~ ✓ shipped (PRs #30–#33 — per-customer-aware box composition) · 🚨 BUG: eggs add-on AND allergies are invisible to the admin during box composition

**Reproduced live during walkthrough.** Anna ordered Klein + Eier (eggs add-on, +EUR 4,50/delivery — captured on `Subscription::preferences->eggs_dairy_add_on = true`).

When the admin opens `/app/admin/boxes/setup/2026-05-04` to compose boxes for the week, the Klein column shows a default product mix (Grünkohl, Kartoffeln, Möhren, Rosenkohl) — **no indicator** that this customer ordered eggs. Same on the per-box detail page (`/app/admin/boxes/N`). Same on the Abos table.

The same problem extends to **allergies** (and likely allergies-note text). The admin can drop a Möhren-line containing celery into a customer's box without any visible warning, even if that customer flagged celery on the preferences step. The katalogzeile-hinzufügen form claims it'll show a warning chip, but the **per-customer allergy** isn't visible while the admin is composing the *size template* — only when adding to one customer's already-saved box.

So Anna pays EUR 24,50/week (20 base + 4,50 eggs), but if the admin doesn't *know* to add eggs to her box, she pays for eggs and doesn't receive them. And if Anna had flagged a gluten allergy, the admin's standard Klein template wouldn't surface it.

**The deeper issue:** the current Box-Setup view is "compose a *size template* once, replicate to all subscribers of that size". That's only valid when all subscribers of a size have identical preferences. As soon as a customer has eggs / allergies / allergies-note, the template-replication assumption breaks.

**Resolution direction:** Two-part fix.

1. **Box-Setup detail view becomes per-customer-aware**, not template-only:
   - Render a column-per-customer (or a stacked panel-per-customer) when there are subscribers with non-identical preferences for that size.
   - Or keep the size-template view as the *starting point*, but show a clearly-labeled `Pro Kunde anpassen` panel underneath for each subscriber who differs (eggs / allergies / allergies-note).
   - Each per-customer panel shows the badge chips: `Eier-Zusatz`, `Allergien: Gluten, Nüsse`, plus the freeform `allergies_note` text. Admin sees at composition time exactly what makes this customer's box different.

2. **At save time, auto-project** customer-specific items:
   - Eggs add-on → automatic position with the configured eggs product.
   - Allergy-conflicting items → either auto-substituted (out of scope, complex) or *flagged for admin attention* with a clear "X violates allergy" warning **before** save, not after.

3. **At the per-box detail view** (`/app/admin/boxes/N`): surface customer preferences prominently at the top — `Kunde: Anna Schmitz · Klein · Eier-Zusatz aktiv · Allergien: Gluten, Nüsse · Notiz: "vegan if possible"`. Admin sees what to put in the box at a glance, and any allergic content gets a warning chip on the position row.

Critical because it directly affects what the customer receives vs what they pay for, and (with allergies) what they can safely eat.

### ~~W2-31a~~ ✓ shipped (PR #29 — admin user detail page surfaces full preference snapshot) · Companion: per-box detail must show *which* customer it's for, with full preference snapshot

Currently the per-box detail page shows just the email + status. Should show the entire customer "shopping cart" we're building for the wizard sidebar (W2-17): name, email, address (delivery), subscription details, allergies, allergies-note, eggs-flag, last 3 deliveries, audit timeline. Same component the customer sees on their dashboard, just from the admin angle.

### ~~W2-32~~ ✓ shipped (small-polish-batch 2026-05-03 — admin "Standard" → "Mittel") · Customer-admin terminology mismatch: "Mittel" (customer) vs "Standard" (admin)

The customer chose `Mittel` on the preferences step. The admin Box-Setup view shows the column as `Standard`. Same enum value (`medium`), different label.

Internal-only impact, but still annoying when triaging customer support — "I have the Standard box" vs "I have the Mittel box" reads as different things.

**Resolution direction:** Pick one label (`Mittel` is the customer-facing one, so admin should match) and use it everywhere. Translation key `admin.box_setup.size_medium` should produce "Mittel".

### ~~W2-33~~ ✓ shipped (PR #29 — `/app/admin/users/{user}` detail page) · 🚨 No per-customer detail page in admin — admin must hop across sections to see one customer's full picture

**User decision (recorded during walkthrough): yes, build this.**

The Abos list (`/app/admin/subscriptions`) shows a row per subscription but the row isn't clickable. To investigate one customer (e.g. Anna), the admin has to:
1. Go to Abos → see Wöchentlich + AKTIV but not size, not eggs, no price
2. Go to Lieferungen → filter for that customer's deliveries
3. Go to Zahlungen → filter for that customer's payments
4. Go to Box-Setup → drill into a week to find their box

There's no "/admin/users/{id}" or "/admin/subscriptions/{id}" detail page that pulls everything together: address, subscription, current box, last 5 deliveries, last 5 payments, audit timeline, allergies, eggs-flag, allergies-note.

**Resolution direction:** Build **one canonical `/app/admin/users/{user}` detail page** that renders the customer's full picture in one place. Three entry points all link to the same page:

1. **Nutzer panel** — make the email cell on `/app/admin/users` a link to the detail page. (Currently it's plain text.)
2. **Abos panel** — make the email cell on `/app/admin/subscriptions` a link too.
3. **Trial-Bestellungen panel** — `/app/admin/orders` (when filtered to `product_type=trial_box`) links the customer cell to the same detail page. The detail page renders a "Trial Box" section with: which size + which preferences they chose, the trial-order, the composed trial-box (if any), payment, delivery status. Same first-class treatment as a subscription, just for the one-off path.

**The detail page sections (rough):**

```
[ Customer header — name, email, phone, signup date, language ]

[ Address(es) ]
  Primary delivery address · gate / floor / instructions

[ Subscription (if any) ]
  Plan · size · cadence · delivery weekday + timeslot
  Eier-Zusatz: yes/no
  Allergien: Gluten, Nüsse  (badges)
  Notiz: "vegan if possible"
  Status · next charge date · monthly recurring

[ Trial order (if any) ]
  Same shape as subscription but for the one-off

[ Boxes — last N saved + upcoming ]
  Per-row: week, size, status, value, eggs-included y/n, link to box detail

[ Deliveries — last N + upcoming ]
  Per-row: scheduled-for, status, address-snapshot, link to delivery detail

[ Payments — last N ]
  Per-row: amount, status, method, paid_at, link to payment detail

[ Audit timeline ]
  Last 20 events tied to this user (uses existing AuditEvent infra)
```

Becomes the **support-ticket landing page**. Admins land here when a customer emails. Single page replaces the four-page hunt.

**Workflow detail:** Admin should also be able to *act* from this page — pause subscription, skip next week, change address — without bouncing to other pages. Phase-2 polish; phase-1 is read-only.

### ~~W2-34~~ ✓ shipped — surfaced on the per-customer detail page; charge advancement guarded by feature tests · BUG?: stale "Nächste Abbuchung" date in Abos table

Maria's row shows `NÄCHSTE ABBUCHUNG · 2026-04-27` (yesterday). Either:
- Charge ran successfully yesterday and `next_charge_date` wasn't advanced after, **OR**
- Charge failed and a retry hasn't happened, **OR**
- Stale fixture data.

Since this is admin-visible *operational* data, a stale date hides a real issue (customer should be charged but wasn't). Admin should be alerted, not just shown an old date.

**Resolution direction:**
1. Verify the dashboard alert ("Fehlgeschlagene Zahlungen in den letzten 7 Tagen: 1") was triggered by Maria — likely yes.
2. Highlight rows with `next_charge_date < today AND status = active` in red on the Abos table.
3. Add a server-side test that subscribes a user, runs the charge command, and asserts `next_charge_date` advances.

### ~~W2-35~~ ✓ shipped (small-polish-batch 2026-05-03 — date glued + S/M/L tooltips) · Box-Setup overview row format quirks

- Date format: `04.05. 2026` — extra space before the year is non-standard.
- `S 0/1` notation (boxes / abos) without a header tooltip — non-obvious. New admin or customer support can't read it.
- "Standard" column header on the Box-Setup detail (W2-32 flavour).

**Resolution direction:** small admin polish — kill the extra space, clarify "S/M/L (Boxen / Abos)" header, rename "Standard" → "Mittel".

### ~~W2-36~~ ✓ shipped (PR #46 — `ensureFirstBoxForActivation` auto-projects on activation) · Box-Setup *Boxen ansehen* shows only saved boxes — admin must remember to hit "Boxen speichern" for new subscriptions

Anna's subscription is active for week 04.05, but `Boxen ansehen` for that week shows only **Maria's** Standard box. Anna's Klein box doesn't materialize until the admin explicitly clicks "Boxen speichern" on the Box-Setup detail page.

The current loop is:
1. New customer subscribes (Anna)
2. Admin must remember to re-open `/admin/boxes/setup/{week}` and click Boxen speichern
3. Box gets created

If the admin had already prepared that week's setup before Anna signed up, **Anna's box will be missing** until someone re-runs the setup. Operationally fragile.

**Resolution direction:**
- When a subscription is activated (sumup_token attached) and within the editable window of an existing week's box-setup, automatically project Anna's box from the saved distribution. Don't make admins click "Boxen speichern" again per new customer.
- OR: the Box-Setup overview page surfaces "1 abo without box for this week" as a warning chip, with a "Box jetzt erstellen" CTA.

### ~~W2-37~~ ✓ shipped (PR #46 — auto-projection + PR #40 orphan-deliveries cleanup + UI surfacing) · Admin Lieferungen view: stale fixture deliveries from May 6 / May 13 + zero deliveries projected for Anna

Anna's first delivery should be projected for the next compositional cycle (Wednesday May 6 at the earliest, given today is Tue Apr 28). The Admin Lieferungen page lists `maria.test 2026-05-06 GELIEFERT` and `ken.klocko 2026-05-13 GEPLANT` (×2) but no row for Anna.

Same root cause as W2-36: deliveries are projected from boxes, boxes haven't been saved for Anna's week, so no `Delivery` row.

This is the W2-22 dashboard-shows-bad-date bug from the customer side: Anna's customer dashboard fabricates `Mo, 27. Apr` because there's no real `Delivery` row to read from.

**Resolution direction:** ties into W2-36 — once new subs auto-project into existing setups, deliveries appear automatically.

---

## Surfaces still unwalked (after this pass)

We now have walked: marketing homepage `/home`, full subscription onboarding, customer dashboard, admin dashboard / Abos / Lieferungen / Box-Setup overview / Box-Setup detail / per-box detail / Nutzer.

Status sweep 2026-05-03:

- **Trial-box flow** (commitment → trial/delivery → trial/payment → trial/complete). Trial pricing is its own thing (`trial_price_oneoff_ttc` = 35 fixed regardless of size). MODELL upsell behaviour (W2-04) needs to be tested in the trial path specifically. **Status:** still unwalked.
- ~~**Marketing pages** other than `/home` (`/about`, `/producers`, `/contact`).~~ ✓ verified built — `marketing/about.blade.php` (286 lines), `marketing/producers.blade.php` (216 lines, DB-backed), `marketing/contact.blade.php` (184 lines). ERD-170/171/172 marked Done.
- ~~**Login + verify-email + forgot/reset/resend flows**.~~ ✓ partially — auth views (`login`, `register`, `forgot-password`, `verify-email`) all branded under `<x-customer-header variant="auth">`; verify-email logout button bug fixed in `fix/secondary-button-default-submit` (2026-05-03). Forgot-password → reset → resend-verification end-to-end as a real returning user still unwalked.
- ~~**Customer subscription edit page** `/app/subscriptions/show`.~~ ✓ touched extensively (price display surfaced via BoxPriceCalculator, heading hierarchy fixed in ERD-119, chapter-tabs nav). Skip/pause/cancel UX still benefits from a deliberate pass.
- ~~**Customer payments history** `/app/payments/history`.~~ ✓ touched (heading hierarchy + chapter-fish hero per W2 redesign followups). Deliberate walkthrough still valuable.
- ~~**Customer profile / account settings** `/account/profile/edit`.~~ ✓ touched (heading hierarchy, danger-zone copy, password-form a11y wiring in ERD-119). Deliberate walkthrough still valuable.
- ~~**Wochen-Katalog** (admin) — catalog editor itself.~~ ✓ walked in admin-ux-walkthrough-followups (empty-state hint, REST/Verbleibend column, Löschen header — all shipped via PR #53 + small-polish-batch).
- ~~**Admin Zahlungen / Anstehende Abbuchungen / Verlauf / Abdeckung Abrechnung**.~~ ✓ walked in admin-ux-walkthrough-followups; vertical-filter-form + CSV-export items still open there.
- ~~**Admin Producers / Products forms** — translation tabs.~~ ✓ shipped + breadcrumbs added in admin-polish-three (2026-05-03). Required-field indicators on the form bodies still on the admin followup list.
- **Email rendering** (verification, charge confirmation, delivery reminder, refund). 4 templates touched in brand-voice batch; full layout-consistency review still pending.
- **Mobile viewport pass** — everything above was at desktop width. Still pending.
- **Real SumUp end-to-end** with a sandbox card. Token storage, first charge, refund. We mocked the token in this walkthrough. Still pending.

---

## Implementation priority

Ordered by combined impact + correctness urgency. Each row maps to one PR-ish unit; numbers in brackets are the W2-XX findings it covers.

| # | Title | Findings covered | Status |
|---|---|---|---|
| 1 | **Pricing reset + central calculator** | W2-16, W2-29, W2-15, W2-23 | ✓ shipped (PRs #43-#45 → BoxPriceCalculator) |
| 2 | **Per-customer-aware box composition: eggs + allergies + allergies-note** | W2-31, W2-31a | ✓ shipped (PRs #30-#33) |
| 3 | **New subscriptions auto-project into existing weekly box-setups + deliveries** | W2-36, W2-37, W2-22, W2-30 | ✓ shipped (PR #46 → ensureFirstBoxForActivation + Subscription::firstExpectedDeliveryDate) |
| 4 | **Per-customer admin detail page (`/app/admin/users/{user}`)** | W2-33, W2-34, W2-31a | ✓ shipped (PR #29) |
| 5 | **Unified customer-facing header (single brand chrome)** | W2-01, W2-02, W2-10, W2-21, W2-26, W2-18 | ✓ shipped (PR #48 → `<x-customer-header>` 3 variants) |
| 6 | **Wizard sidebar: progressive summary of everything gathered** | W2-17, W2-06, W2-13 | ✓ shipped (PR #47 → `<x-onboarding.summary>`) |
| 7 | **MODELL flow correction** | W2-04 | ✓ shipped (PR #54 → preferences-step uses `OnboardingDraftManager::nextStep()` instead of hardcoding commitment, and commitment-show bounces forward when intent is already set) |
| 8 | **Wizard step trim + step indicator polish** *(no reorder)* | W2-03, W2-08 | ✓ shipped (PR #54 → 4 visible segments: Konto · Adresse · Box · Zahlung; the actual 5–6 internal steps still run) |
| 9 | **Box-size cards: clearer "Pro Lieferung" + brand visual treatment** | W2-11, W2-12 | open — copy reads "EUR :amount / Lieferung" via `box_size.price_per_delivery` (preferences step). **Photographic crate-illustration direction was tried in PR #55 and rolled back in PR #56** (didn't land aesthetically). Future pass should explore a different visual direction (stylized SVG silhouette? typographic numerals? different photo style?) before committing assets across surfaces. |
| 10 | **Brand-new-subscriber dashboard hero + verify-email continuity** | W2-24, W2-25, W2-20 | ✓ shipped — hero shipped (PR #46+#49); verify-email already routes through `<x-customer-header variant="auth">` (PR #48) so the "fifth standalone layout" ask is closed. |
| 11 | **Schedule "Keine Präferenz" trap** | W2-14 | ✓ shipped (PR #54 → controller pre-selects first valid weekday + timeslot; placeholder option dropped from the markup) |
| 12 | **Auth-state-aware wizard header** | W2-07 | ✓ shipped via PR #48 — wizard variant of `<x-customer-header>` only renders the logout link when `@auth`, never an "Anmelden" link. |
| 13 | **Orphan-user cleanup** | W2-27 | ✓ shipped (PR #54 → `users:cleanup-orphans` Artisan command with --older-than-days + --dry-run; daily cron suggested in the README) |
| 14 | **Final review / payment-page summary placement** | W2-28 | ✓ shipped (PR #49 → payment-step uses `<x-onboarding.summary>` as a review card) |
| 15 | **Admin terminology + label polish** | W2-32, W2-35 | ✓ shipped — `admin.box_setup.size_medium` "Standard" → "Mittel/Medium/Moyenne", compact-grid date "04.05. 2026" → "04.05.2026", S/M/L tooltips on the column headers (small-polish-batch on 2026-05-03). |
| 16 | **Dev-env: real CSP fix for Vite + retire `npm run build` workaround** | W2-DEV-02 | ✓ shipped — extracted CSP into `<x-csp>` Blade component used by all 6 layouts; component appends `http://127.0.0.1:5173` + `ws://127.0.0.1:5173` to script-/style-/connect-src when `app()->environment('local')`. `npm run dev` HMR now works without the build workaround (dev-and-admin-polish 2026-05-03). |

**Bonus shipped, not on the original list:**
- W2-DEV-01 (PHP 8.5 deprecation notices in HTML) — addressed via dev-only suppression (PR #28)
- W2-37 / orphan-deliveries (validation + cleanup command + UI surfacing) — PR #40
- W2-31 companion (live single-box editor as Alpine surface) — PR #39
- Vorausplanung-Übersicht trials + eggs + revenue total (PR #38)
- Slim Testbestellungen page after Box-Setup unification (PR #37)
- Currency formatting helper sweep (PR #51 → `App\Support\Money::format` + `@money` directive across all currency render sites)
- Cancel-modal default focus (verified — `<x-secondary-button>` already carries `data-default-focus`)

PRs 1–3 are the correctness/operational batch (pricing + add-on integrity + composition pipeline). 4–5 are the design + flow rebuild. 6–9 are wizard/onboarding cleanup. 10+ tail-end + dev-env.

**Next batch candidates (post-W2-stack-merge, 2026-04-29):**
- ~~**#8** Wizard step trim + step indicator polish~~ ✓ shipped (PR #54 — 4 visible segments).
- ~~**#11** Schedule "Keine Präferenz" trap~~ ✓ shipped (PR #54 — controller pre-selects first valid).
- ~~**#15** Admin terminology polish — "Standard" → "Mittel"~~ ✓ shipped (small-polish-batch 2026-05-03).
- ~~**#7** Drop MODELL step from subscription flow~~ ✓ shipped (PR #54).
- ~~**#10** Verify-email continuity~~ ✓ shipped (PR #46 + #48 + #49).

Smaller polish from the followup docs:
- ~~Schedule-step "PLANEN" → "ZEITPLAN"~~ ✓ already correct (`lang/de.json:Schedule = Zeitplan`; uppercase via Tailwind).
- ~~Skip-card week range "4. Mai" → "4.–10. Mai"~~ ✓ already implemented (`subscriptions/show.blade.php`).
- ~~Übersicht trial-pipeline empty-state~~ ✓ already implemented (`admin.trials.pipeline_*_empty` keys).
- ~~Wochen-Katalog empty-state confusion~~ ✓ shipped (PR #53 — banner above the table).

**Open questions resolved (2026-04-28):**
- ~~Step 7 — reordering wizard~~ → **decision: keep the current step order**. Konto/Adresse/Vorlieben/Modell/Zeitplan/Zahlung stays as-is. The "trim to four steps" part of W2-03 still stands; the *order* doesn't change.
- ~~Step 14 — admin per-customer detail page~~ → **decision: build it**. Scoped up to also cover the trial-box path (W2-33 expanded). Three entry points (Nutzer, Abos, Trial-Bestellungen) all link to the same page.
