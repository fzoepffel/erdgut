# Erdgut – UX / Flow Review Notes

Live working document. Findings collected by walking the full flow as a
new subscriber (Sabine), a new trial customer (Tobias), and an admin
(Aurelia). Severity is a quick judgment, not a SLA.

Severity legend: **High** = breaks flow / hides money / data risk · **Med** = friction / confusion · **Low** = polish.

---

## TL;DR — what I'd fix first

The product is rich and many individual screens are well-thought
(weekly catalog, box-setup overallocation guard, billing coverage
matrix, payment detail page, helper text everywhere). But the **state
that ties customer ↔ admin together is fragile**. Two sources of truth
diverge silently, and the customer is the one who sees the wrong one.

The seven things to address before launch:

1. **One single status per delivery.** Today
   `Order.status`, `Subscription.status`, `Delivery.status`,
   `Payment.status`, `Box.status` all coexist and don't auto-sync. Pick
   a reducer (`stateFor(user, week)`) and bind every UI string to it.
   See **K6, D1, D2, I4, J1, J9, K17.**
2. **Ship localised validation + status labels.** German locale, but
   raw English leaks through (`The recipient name field is required`,
   `IN TRANSIT`, etc.). One PR fixes ~30 small symptoms. See **B4, F1, K3, K12.**
3. **Stop letting the user pick something we can't deliver.** Slot
   pickers offer Mo-Fr × 3 timeslots; reality is Mittwoch 12-16. The
   delivery card on the customer side then displays the bogus pick. See **D2, C3, K5, B5.**
4. **Don't promise dates that aren't in the DB.** Hero says "Erste Box
   am 6. Mai" with zero `Delivery` rows, then keeps saying it after a
   real Box for 13. Mai is created, even after the user skips that
   week. See **D1, K8, K17.**
5. **`subscription_id` and `next_charge_date` MUST be set on
   activation.** Without them: customer's box doesn't render, customer
   never sees first-charge date, scheduler skips the customer, admin
   "Anstehende Abbuchungen" hides them. See **D1, K9, K10, J4, J8.**
6. **Validate the eggs-allergy + eggs-addon collision at the
   subscription level.** It survives onboarding ("EIER: Ja /
   ALLERGIEN: Eier" shown side by side), surfaces only at box-setup,
   and after the admin marks it OK the eggs disappear silently while
   the customer keeps paying for them. See **B14, D3, F1, H9, K14.**
7. **Skip-week deletes the box even when it's been delivered.** Loses
   history; orphans the existing Delivery; admin then sees "QUELLE:
   Verwaist" and the customer's dashboard still shows the deleted
   week's delivery as "Nächste". See **K7, K15, K16.**

Everything else in this doc are smaller findings — useful, but the
above seven are the foundation.

---

## A. Public marketing & home

(The Coming Soon page at `/` is intentional — excluded from review.)

### A1. "Kapitel" tiles link to authenticated `/app/products` — 404-ish for visitors **[High] · DONE (fix/erd-ux-public-products-and-onboarding-header)**

Resolved by making the catalogue itself public: the new `public.products.index`
route at `/products` (and `public.products.show` at `/products/{product}`)
share a controller with the auth-gated `/app/products` route but render the
marketing layout for guests. Chapter tiles now link straight to the public
URL with a category filter; logged-out visitors land on a real product
listing instead of a login redirect.
Tiles 01–05 (Obst & Gemüse, Fleisch, Fisch, Eier & Milch, Brot) on `/home` link to
`/app/products?category=…`. Those are protected by `auth+verified`. A logged-out
visitor clicking these is redirected to `/auth/login` and the category context is
lost — they don't even land back in the right place after login.
- **Fix**: either build a public catalog teaser at `/produkte/{kategorie}` (preferred,
  the hero literally promises a chapter to view), or change the tiles to link to
  `/about#kategorie-…`, or guard the link with a "Login um zu sehen" affordance.
- **File**: `resources/views/marketing/home.blade.php` (chapter section).

### A2. Header "Registrieren" route stays separate from hero "Abonnieren" — **decision: keep as-is**
- `/auth/register` (header button) vs `/onboard?intent=subscription` (hero CTA)
  intentionally serve two different mindsets.
- **Why**: we want visitors to be able to create an account before
  committing to a box/subscription, so we can capture interested-but-not-yet-
  ready emails. The header "Registrieren" is the low-commitment entry point;
  the hero CTAs ("Testbox bestellen" / "Abonnieren") are the high-commitment
  entry points. Both must coexist.
- **Action**: none. Do not change the header CTA route. If the simultaneous
  visibility looks confusing later, we can revisit by tightening copy
  rather than collapsing the routes.

### A3. Marketing copy on /home implies trial = fixed 35 € — but trial offers all 3 sizes **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
The trial section on `/home` says: "EUR 35 für eine einzelne Erdgut-Box —
eine Lieferung, kein Abo, keine gespeicherte Karte." This implies the trial
is a fixed-price 35 € (Mittel) box. In reality, the trial wizard
(`/onboard/preferences`) offers all three sizes: Klein 20 €, Mittel 35 €,
Groß 42 € — and per product owner this is the desired behaviour
(customers should be able to pick size for the trial too).
- **Target German copy** (per product owner):
  > "Ab EUR 20 für eine einzelne Erdgut-Box — du wählst die Größe und den
  > Termin, eine Lieferung, kein Abo, keine gespeicherte Karte."
- The amount should derive from `config('box.included_value_by_size.small')`,
  not `config('box.trial_price_oneoff_ttc')`, so the copy auto-tracks the
  smallest box price.
- **File**: `resources/views/marketing/home.blade.php`, "OHNE
  VERPFLICHTUNG" section.

## B. Subscription onboarding (Abonnieren)

### B1. "Abmelden" link visible in onboarding header — easy to lose progress **[Med] · DONE (fix/erd-ux-public-products-and-onboarding-header)**

Wizard chrome is now strictly nav-free. The logout link previously rendered
in the wizard header is removed. The onboarding draft survives a closed tab,
so a customer who needs to step away can resume via /onboard. Real logout
remains reachable from any post-onboarding page.
The marketing header is rendered on the onboarding wizard pages. After step 1
the user is technically logged in, so an "Abmelden" link appears. Clicking it
during step 2/3/4 logs the user out and (because of the public-home redirect
chain) sends them to `/`. They can resume by re-logging-in, but it's not
obvious — and they get no warning.
- **Fix**: hide nav (or at least the Abmelden control) on `/onboard/*` pages,
  or replace it with a "Onboarding pausieren" link that explains state. The
  wizard layout should be its own focused shell.

### B2. Step indicator label disagrees with submit-button label **[Low]**
On the address step, the progress bar's next pill is "03 BOX" but the submit
button reads "Weiter zu Vorlieben". The wizard internally has a separate
"preferences" step, but the indicator collapses preferences + commitment +
schedule into a single "BOX". The button promises "Vorlieben"; the next
indicator promises "BOX". Pick one taxonomy.
- **Fix**: either expand the indicator to show all real steps (Konto · Adresse ·
  Vorlieben · Verpflichtung · Plan · Zahlung), or rename the step button to
  "Weiter" without naming the next destination. Inconsistency makes it look
  buggy.

### B3. Email verification gate is inconsistent across the wizard **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
- During onboarding the wizard does NOT require email verification: a user
  can fill account → address → preferences → ... without ever clicking the
  email link.
- But if they log out (or the session expires) and log back in, the
  `verified` middleware blocks them at `/auth/verify-email` and they cannot
  return to the onboarding flow.
- Net effect: a user who closes the tab mid-onboarding loses access to
  onboarding until they verify the email. That mail may be in spam, may not
  have arrived, etc.
- **Fix**: pick one rule. Either (a) require verification at the start of
  onboarding (before account step accepts data — best for security and
  data hygiene), or (b) allow re-entry into onboarding for unverified
  users (white-list `/onboard/*` from the `verified` middleware). Don't
  silently change behavior between first-time and return-to.

### B4. Validation error messages are in English under German locale **[High] · DONE (fix/erd-ux-localised-labels-and-validation)**
On the address step (and step 1) submitting empty / invalid forms produces
default Laravel English messages with raw snake_case field names:
- "The recipient name field is required."
- "The street line 1 field is required."
- "The postal code field is required."
- "The city field is required."
- "The email has already been taken."
The locale is `de` and the rest of the page is German — these stick out.
- **Root cause**: `app/Http/Requests/Onboarding/AddressStepRequest.php` has
  no `attributes()` and no `messages()`, and `lang/de.json` ships no
  validation strings.
- **Fix**: ship `lang/de/validation.php` (the standard Laravel localized
  validation file) plus `attributes()` per FormRequest mapping
  `street_line_1` → "Straße und Hausnummer" etc. Same approach across all
  FormRequests project-wide.

### B5. Address form has zero geo-validation — Berlin-only service accepts any junk **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
The address form accepted PLZ `ABC123XYZ`, city `Pjöngjang`, country `DE`
and saved the record. There's no:
- format check on PLZ (German PLZ is 5 digits, regex `^\d{5}$`),
- range check for Berlin coverage area (10115–14199 etc., or whatever the
  current delivery zones are),
- consistency check between PLZ and city.
For a Berlin-only delivery service that depends on accurate addresses for
hand-off to drivers, this is a real data-integrity problem (and breaks the
"in ganz Berlin beliefert" promise on the homepage).
- **Fix**: add a rule on `postal_code` like `regex:/^\d{5}$/` plus a
  Berlin-zone whitelist (or a `Rule::in(...)` from a configurable list).
  Show a friendly warning above the form: "Aktuell liefern wir nur
  in Berlin (PLZ 10115–14199)."

### B6. "E-Mail bereits vergeben" gives no recovery link **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
When duplicate email is detected at step 1 the inline error reads
"The email has already been taken." The user might have an account already
and just not remember. The error should offer a one-click recovery:
"Diese E-Mail ist bereits registriert. **Hier anmelden** · **Passwort
zurücksetzen**". This is a standard pattern that reduces drop-offs.

### B7. Address form: country chooser is hidden but visually present **[Low] · DONE (fix/erd-ux-wizard-polish-batch)**
The country block is rendered visually as "LAND: Deutschland" but the
`country_code` input is `type=hidden`. Users can't change it. Either make
that explicit ("Wir liefern aktuell nur in Deutschland") or remove the
visual block entirely. As-is it looks like an interactive field that
mysteriously can't be edited.

### B8. Autocomplete attributes — well done ✓
All address fields carry the right `autocomplete` tokens
(`name`, `address-line1`, `address-line2`, `postal-code`,
`address-level2`, `tel`). Browser autofill will work.

### B9. Mobile wizard header is clean — desktop should follow **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
On a 375-wide viewport the wizard header is just the logo (no Anmelden,
no language switcher, no Abmelden). That focused shell would improve the
desktop wizard too. Keep nav-free during onboarding regardless of width.

### B10. "Lieferhinweise" appears twice — schedule step overwrites address step **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
The address step (`/onboard/address`) has a `Lieferhinweise` textarea
saving to `addresses.delivery_instructions`. The schedule step
(`/onboard/subscription/schedule`) has another `Lieferhinweise` textarea
that, if filled, **overwrites** the address one (see
`Onboarding/Subscription/ScheduleStepController.php`). Two textareas, no
sign of carry-over, no warning that you're overwriting earlier text.
- **Fix**: pick one home for delivery instructions (the address record).
  Pre-fill the schedule step's textarea with the address's value, or
  remove the textarea from the schedule step entirely.

### B11. Step-bar labels disagree with reality (4 pills, 5 real steps) **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
The progress bar shows 4 pills (KONTO · ADRESSE · BOX · ZAHLUNG) but
there are at least 5 real steps for subscription onboarding (account,
address, preferences, schedule, payment) — and the button text moves
from "Vorlieben" → "Zeitplan" → "Zahlung", spelling out 3 distinct steps
that the bar collapses into "BOX". Pick one taxonomy and apply it
consistently to the bar AND the button labels.

### B12. Payment step has no T&Cs / privacy / mandate consent **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
At the SumUp setup step the user is about to authorise a recurring
charge. There is no checkbox accepting Erdgut's AGB, privacy policy, or
recurring-billing mandate. The link at the bottom is to SumUp's policy.
For SEPA-style recurring auth in Germany this is risky from a consumer-
protection standpoint and probably required by Datenschutz law.
- **Fix**: add a required checkbox above "Speichern" linking to AGB &
  Datenschutz; only enable the SumUp save button when checked.

### B13. Final submit button reads "Speichern" — not "Abo starten" **[Low] · DONE (fix/erd-ux-wizard-polish-batch)**
The user is committing to a recurring subscription, not just saving a
card. "Speichern" makes it sound like the card is saved without
starting the subscription.
- **Fix**: rename to "Karte speichern und Abo starten" or similar.

### B14. "Für zukünftige Zahlungen speichern" toggle is shown as optional **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
For a subscription this toggle MUST be on. Showing it as a switchable
control invites the user to flip it off — and at best the flow then
breaks; at worst they think they avoided being billed.
- **Fix**: hide the toggle on the subscription path (always on), or
  display it as locked-on with a tooltip "Für ein Abo erforderlich".

## C. Trial order (Testbox)

### C1. Trial flow reuses the subscription account/address steps **[Low]**
For a "no commitment" trial the user must fill the same Konto + Adresse
forms a subscriber fills. There's no guest-checkout, no email-only
purchase, and no visual signal that this is a one-off. For the MVP
that's a reasonable trade-off (we want a user record so we can email
delivery confirmations etc.), but worth noting.

### C2. Trial preferences uses subscription pricing label "PRO LIEFERUNG" **[Med] · DONE (fix/erd-ux-customer-copy-batch)**
On `/onboard/preferences` (shared between trial + subscription) the box
size cards read "20,00 € PRO LIEFERUNG", "35,00 € PRO LIEFERUNG",
"42,00 € PRO LIEFERUNG". For a trial, "PRO LIEFERUNG" is misleading
(implies recurring). The summary in the right-rail also says "EUR
35,00 / Lieferung".
- **Fix**: when `intent=trial`, render the price as "EUR 35,00 ·
  einmalig" (or similar). The `Onboarding/PreferencesStepController`
  already knows the intent — branch the label.

### C3. Trial delivery picker offers 3 timeslots — only one is real **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
Same as D2 (subscription): the picker presents 08-12, 12-16, 16-20 but
the real fleet runs Mittwochs 12-16. For a trial, where date+slot is
manually confirmed within 14 days, this matters less, but it still
sets up false expectations.
- **Fix**: gray out / hide the unsupported slots, OR add a one-line
  helper "Aktuell liefern wir Mittwochs zwischen 12 und 16 Uhr — wir
  bemühen uns, deinen Wunschzeitraum so gut wie möglich zu treffen."

### C4. "Wunschwoche oder -Datum" is a free-text field with no validation **[Med] · DONE (fix/erd-ux-wizard-polish-batch)**
Users can type anything ("nächste Woche", "8.4.", "asap", "in zwei
Monaten"). Operators have to read free-form German and translate to a
real date.
- **Fix**: replace with a date picker (or a "frühestens am …" picker
  scoped to the next 28 days), keeping a small free-text "Anmerkung"
  alongside.

### C5. "Sample" date "nach dem 6. Mai" hardcoded in placeholder **[Low] · DONE (fix/erd-ux-customer-copy-batch)**
The placeholder of the Wunschwoche field reads "z.B. nächste Woche, nach
dem 6. Mai". 6. Mai becomes meaningless once we're past that date.
- **Fix**: compute the placeholder dynamically: "z.B. nächste Woche,
  nach dem {today+5}".

### C6. Delivery-step summary says "EUR 35,00 / Lieferung" for a one-off **[Med] · DONE (fix/erd-ux-customer-copy-batch)**
On `/onboard/trial/delivery` the right-rail "Deine Box" shows
"Mittel · ~11 Artikel · EUR 35,00 / Lieferung" — for a trial the unit is
misleading. See C2.

### C7. Trial payment step is well-explained ✓
`/onboard/trial/payment` reads "Einmalige Zahlung von EUR 35,00. Kein
Abo, keine gespeicherte Karte." The order number is shown and the
3-step "WAS ALS NÄCHSTES PASSIERT" panel explains 3DS + dispatch.
Significantly clearer than the subscription payment step.

### C8. "Geht Montag raus" on payment step contradicts other copy **[Low] · DONE (fix/erd-ux-wizard-polish-batch)**
Step 3 of the payment explainer says "Deine Probe-Kiste geht Montag raus —
Wir stellen sie Donnerstag zusammen, liefern sie nächste Woche." The
customer-facing copy elsewhere says delivery is Mittwoch 12-16. "Geht
raus" probably means "leaves the warehouse Monday" rather than
"delivered Monday", but the phrasing is ambiguous and conflicts with
"liefern sie nächste Woche" (which doesn't specify a day either).
- **Fix**: standardise on one wording, e.g. "Wir stellen sie Donnerstag
  zusammen und liefern sie am darauffolgenden Mittwoch."

### C9. Trial completion page is good ✓
"Alles erledigt!" + Bestellung # + Bezahlt + ZUM DASHBOARD button +
poetic closing line. Clear and reassuring.

### C10. Trial-user dashboard adapts the navigation ✓
Trial users see only Dashboard + Produkte (no Abo, no Lieferungen). Good
role-based UX.

### C11. Trial-user dashboard upsell copy contradicts the trial promise **[High] · DONE (fix/erd-ux-customer-copy-batch)**
The Probebox card on the trial dashboard offers
"MACH AUS DEINER TESTBOX EIN WÖCHENTLICHES ABO" with the helper
"Keine neue Karte nötig — wir übernehmen Adresse und Vorlieben."
But the trial flow uses `createOneTimeCheckout(... customerId: null ...)`
in `TrialOrderService` — i.e. **no card is saved**. So a new card IS
required to start the abo.
- **Fix**: change copy to "Adresse und Vorlieben übernehmen wir — Karte
  hinterlegen ist der einzige zusätzliche Schritt." Either that, or
  decide to start saving the SumUp customer/token on the trial purchase
  (with explicit consent at the trial payment step).
- **File**: dashboard view template for trial users (look for
  "Mach aus deiner Testbox").

### C12. "Noch kein Abo — ABO STARTEN" double-CTA ✓
Same dashboard has a second prominent "ABO STARTEN" link in the AUF
EINEN BLICK row. Two visible upsells but they don't crowd the page.
Fine.

### C13. Trial completion subtitle "ab der nächsten Lieferung" **[Low] · DONE (fix/erd-ux-customer-copy-batch)**
Bottom of trial completion page: "Ein Platz am Tisch — deiner, ab der
nächsten Lieferung." The phrase "ab der nächsten Lieferung" implies
recurring relationship, but the user just bought a one-off. Could
confuse the customer about whether they're now subscribed.
- **Fix**: tweak to "Ein Platz am Tisch — deiner, mit deiner ersten
  Box."

## D. Customer dashboard & account

### D1. Hero promises a delivery date that doesn't exist in DB **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
Dashboard hero of a freshly-onboarded subscriber says
"Deine erste Box kommt am Mi, 6. Mai" while:
- The Lieferungen empty state says "Keine Lieferungen im Kalender."
- The "Diese Woche" card says "Keine Box für diese Woche."
- The DB has 0 deliveries and 0 boxes for the user.

The "first box" date is a calculated promise that isn't backed by any
record. If onboarding finishes after the cut-off, or if no driver covers
the user's PLZ, the user will still see this date and feel deceived.
- **Fix**: only show "Erste Box am …" once an actual `Delivery` row exists
  for the user. Otherwise show a softer "Wir planen deine erste
  Lieferung – wir melden uns sobald sie bereit ist."

### D2. Promised delivery weekday/timeslot don't match what is offered **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
The onboarding wizard let the user pick `Montag, 08:00–12:00`. After
onboarding:
- Dashboard says "donnerstags für die folgende Montagslieferung" but the
  hero says "Mi, 6. Mai" → user got Wednesday, not Monday.
- `/app/deliveries` hero says "Mittwochs zwischen 12 und 16 Uhr fährt
  unser Lieferwagen los." → only Wednesday 12-16 is a real slot.
The wizard offers Montag-Freitag and three timeslots; in reality only
Mittwoch 12-16 is delivered. The user's preferences are silently
ignored.
- **Fix**: in the schedule step, only show actual delivery zones (or
  rename the picker explicitly to "Bevorzugter Tag, falls möglich" with a
  note "Aktuell liefern wir nur mittwochs 12-16 in Berlin"). Don't
  collect a preference you cannot honour.

### D3. Subscription page shows EIER:Ja and ALLERGIE:Eier side by side **[High] · DONE (fix/erd-ux-trust-coherence-batch)**
On `/app/subscriptions` Vorlieben tab the user can see:
- EIER: Ja (the +4,50€ add-on)
- ALLERGIEN: Eier, Schalenfrüchte
i.e. user is signed up to receive eggs while declaring eggs as an
allergy. The wizard accepted the conflict; the dashboard accepts it; the
operator will likely deliver eggs by mistake to an allergic customer.
- **Fix**: hard-block on the Vorlieben step — if `eggs` is in the
  allergy list, disable the Eier add-on (or vice versa). Same constraint
  on the Abo edit page.

### D4. "Nächstes Abbuchungsdatum: Wird nach der ersten Abbuchung festgelegt" **[Med] · DONE (fix/erd-ux-customer-copy-batch)**
On the Abo → Zahlungen tab. Circular text — the user is asking when the
first charge happens, and the answer is "after the first charge". For a
subscription customer this is the most important transparency moment.
- **Fix**: compute and show "Erste Abbuchung am [Datum] · X € auf Karte
  endend …42." Even an estimate is better than the current copy.

### D5. Lieferungen empty-state copy contradicts current state **[Low] · DONE (fix/erd-ux-customer-copy-batch)**
"Keine anstehenden Lieferungen — Wenn dein Abo aktiv ist, erscheinen
deine Lieferwochen hier." — but the abo IS active. Conditional reads as
if the user is not yet a subscriber.
- **Fix**: drop the "Wenn dein Abo aktiv ist" clause; replace with
  "Sobald wir die Box für deine Lieferwoche zusammenstellen, erscheint
  sie hier."

### D6. Produkte filter chips don't match marketing "Kapitel" taxonomy **[Med] · DONE (fix/erd-ux-category-taxonomy-batch)**
- Marketing home Kapitel: **Obst & Gemüse · Fleisch · Fisch &
  Meeresfrüchte · Eier & Milchprodukte · Brot** (+ Produzenten + Almanach).
- `/app/products` filter chips: **Backwaren · Fisch · Fleisch · Gemüse ·
  Milchprodukte · Obst**.
Two different category schemes for the same content. The user who saw
"04 Kapitel: Eier & Milchprodukte" on the home now has to map that to
"MILCHPRODUKTE" because there's no "Eier" chip — and finds eggs filed
under MILCHPRODUKTE rather than the named chapter.
- **Target end state (per product owner)**: a single chip
  **"Eier & Milchprodukte"** that holds eggs + dairy. Rename the rest of
  the chips to match the marketing Kapitel labels exactly:
  Obst & Gemüse · Fleisch · Fisch & Meeresfrüchte ·
  Eier & Milchprodukte · Brot. Drop the separate Backwaren label
  (use "Brot") and merge Obst+Gemüse into one chip.

### D7. "Bio-Eier" labeled MILCHPRODUKTE — should be "Eier & Milchprodukte" **[Low] · DONE (fix/erd-ux-category-taxonomy-batch)**
The "Bio-Eier" product card is tagged "MILCHPRODUKTE". Per D6 the target
end state is to label both eggs and dairy as "Eier & Milchprodukte".
- **Fix**: rename the existing `dairy` (or equivalent) category to
  `eggs_and_dairy` with display label "Eier & Milchprodukte"; route eggs
  to that category by default. No need for a separate "eggs" enum.

### D8. "Sabine Subscriber" wraps to two lines in nav at narrow widths **[Low] · DONE (fix/erd-ux-dashboard-density-batch)**
Username button can wrap mid-name when there's not enough room on the
right side. Truncate with ellipsis, or display only first name in the
top bar.

### D9. Long blank stretches between dashboard sections **[Med] · DONE (fix/erd-ux-dashboard-density-batch)**
At normal scroll positions the screenshot shows multiple full viewport
heights of empty cream background between content cards. Combined with
the tall hero, the dashboard's information density is very low — the
user has to scroll a lot to find action items. Tighten section spacing
or move the actionable cards (next delivery / abo status) above the
fold.

### D10. (n/a) Producers can come from anywhere in Europe — only customers/delivery are Berlin
Earlier note about "Hof am Tegernsee" being out-of-region was based on a
wrong assumption. Per product owner, producers are sourced from across
Europe; only the customer-side delivery is Berlin-only. Keep this in
mind for any "regional"/"Berlin"-flavoured copy on the customer side —
say "im Rhythmus der Saison" rather than "regional aus Berlin"
when describing producers, to avoid implying farm-to-Berlin
geography. (Spot-check marketing copy on `/home`: phrases like "von
Erzeugern, die wir mit Namen kennen" are fine; phrases like "aus der
Region" should be reviewed.)

### D11. Header user button is the only nav item that opens a dropdown **[Low] · DONE (fix/erd-ux-dashboard-density-batch)**
"Sabine Subscriber" expands to {Zahlungen, Liefervorlieben,
Kontoeinstellungen, Abmelden}. Three of those four are the only way to
reach important pages (account settings, delivery preferences) — they
deserve to be discoverable. Either flatten one or two into the main nav,
or add a clearer label to the dropdown trigger ("Mein Konto").

## E. Admin – producers

### E1. Terminology drift: Produzent / Erzeuger / "Probierbox" — pick one **[Med] · PARTIALLY DONE (fix/erd-ux-admin-polish-batch)**

Admin (operational) surfaces now consistently use "Produzent". Marketing
copy intentionally keeps "Erzeuger" for brand voice (warmer, more
agricultural). The "Testbox / Probebox / Probierbox / Probebestellung"
wing is still open — needs a separate decision and sweep.
The admin section uses three+ different German nouns for the same thing,
sometimes on adjacent screens:
- Sidebar: **Produzenten**, list page title: **Produzenten**, list-page CTA:
  **Produzent anlegen**.
- Form heading: **Neuer Erzeuger**, form CTA: **ERZEUGER ANLEGEN**, success
  banner: "**Erzeuger** "Spargelhof Beelitz" wurde angelegt".
And in trial/test land:
- Customer marketing copy: **Testbox** (homepage), **Probebox** (trial
  dashboard), **Probelieferung**.
- Admin: **Probierbox / Probierbestellungen** (dashboard hinweise),
  **Testbestellungen** (sidebar + section header).
- **Fix**: pick one canonical term per concept and use it across user-
  facing + admin: e.g. **Produzent** for producers, **Probebox /
  Probebestellung** for the trial. Codify in a glossary doc and grep
  for the others.

### E2. Admin shell exposes Abmelden as the "primary" submit on every page **[Med] · DONE (fix/erd-ux-admin-polish-batch)**
Same as B1 / D8 family: the admin sidebar's "Abmelden" link is a POST
form. A generic `button[type="submit"]` selector on any admin page
matches this button as an alternative submit, and a casual click can log
the admin out mid-task. For users this means easy accidental logout if
they tab-tab-Enter looking for the actual form submit.
- **Fix**: render the "Abmelden" submit with a distinguishable role
  (`form[data-logout]` or wrap it in an `<aside>` outside the main
  forms), or add an `aria-label` and `tabindex=-1` so it isn't the next
  reachable submit when the user keyboard-focuses.

### E3. "Alle drei Sprachen sind erforderlich" but tabs aren't marked **[Med] · DONE (fix/erd-ux-admin-form-clarity-batch)**
On `/app/admin/producers/create` the user has to fill DE + EN + FR;
filling only DE submits but returns the validation banner "Alle drei
Sprachen sind erforderlich." However, the EN and FR tabs aren't
visually marked as containing missing required fields — admins have to
click each tab to find what's missing. Easy to fix.
- **Fix**: badge each tab with a red dot if any required field in that
  language is empty. Same pattern needed wherever the multi-language
  form pattern is repeated (Produkte etc.).

### E4. Producers without products show no warning **[Low] · DONE (fix/erd-ux-admin-form-clarity-batch)**
"Imkerei Sonnenfeld" sits in the list with PRODUKTE = 0 and no visual
hint. A producer with no products won't appear on the public producers
page (and won't end up in any box). Worth a small "0 — Produkte
ergänzen" hint or muted styling so admins know to act.

### E5. Destructive "Löschen" button is the rightmost / largest action **[Low] · DONE (fix/erd-ux-admin-form-clarity-batch)**
Each producer row has an inline "Löschen" button rendered in red,
without an inline confirmation step (need to verify if a JS confirm
fires; a one-line note at minimum). Adjacent to "Bearbeiten" which is
plain text — easy fat-finger. Make Löschen a less prominent action
(icon-only?) or require a confirm modal with the producer name typed
in.

### E6. Producer create form lets you upload an image AND set fallback URL ✓
Nice fallback pattern; the helper text "Wird nur verwendet, wenn oben
keine Datei hochgeladen ist." makes intent explicit.

### E7. Producer can be marked Testdaten ✓
Useful for keeping seed/dev rows out of production reports. Keep using
this convention everywhere (orders, products etc. already have it).

## F. Admin – products

### F1. Allergen labels disagree between admin and customer **[Med] · DONE (fix/erd-ux-localised-labels-and-validation)**
- Customer onboarding (Vorlieben step) shows: **Soja**, **Krustentiere**.
- Admin product form (Eigenschaften → Allergene) shows: **Sojabohnen**,
  **Krebstiere**.
Both spellings are correct German for the same EU-regulated allergen but
operators and customers may disagree about what was selected, especially
in support tickets.
- **Fix**: align labels — choose one form per allergen and use it both
  in `resources/lang/de.json` (or wherever labels are defined) and
  in the admin form. The official EU naming uses "Krebstiere" and
  "Sojabohnen", so probably standardise on those.

### F2. KATEGORIE select defaults to "Backwaren" on Produkt anlegen **[Med] · DONE (fix/erd-ux-admin-polish-batch)**
The Kategorie dropdown is rendered with "Backwaren" preselected. The
admin can save a Spargel as "Backwaren" with one click of the submit
button. There is no validation that catches a wrong category (it's a
valid enum value).
- **Fix**: render with a placeholder option ("— Kategorie wählen —")
  and require an explicit choice.

### F3. ERZEUGER select isn't searchable for long lists **[Low]**
The producer dropdown is a plain `<select>`. Once the catalog grows past
~30 producers it'll be hard to find one. Switch to a typeahead /
combobox.

### F4. Helper text in form is excellent ✓
Several fields have extremely useful inline help: "Kundenverkaufspreis";
"Was wir dem Produzenten zahlen. Wird für die Margenauswertung
verwendet."; "Veröffentlicht: im Shop und in der Standard-Box sichtbar.
Verfügbar: wird im Shop angezeigt, sofern veröffentlicht.";
"Wenn aktiv, wird dieses Produkt aus dem Standard-Größen-Template
ausgeschlossen ..."; "Alt-Texte können nach dem Speichern beim Bearbeiten
ergänzt werden." Keep this density of helper text in other admin forms.

### F5. is_published / is_available / is_seasonal triple is subtle **[Med] · DONE (fix/erd-ux-admin-form-clarity-batch)**
A product has three boolean toggles whose interaction is non-trivial:
`is_published`, `is_available` (im Shop verfügbar), `is_seasonal`. The
form helper explains "Veröffentlicht" + "Verfügbar" but not
"Saisonal", and the *interaction* between all three (e.g. published
and seasonal but not available) isn't explained.
- **Fix**: a small truth table somewhere in the admin docs (or a
  collapsible "Wann sehen Kund:innen das Produkt?" panel under the
  toggles).

### F6. List view doesn't show Einkaufspreis or margin **[Low] · DONE (fix/erd-ux-admin-form-clarity-batch)**
The products list shows only Preis (= Stückpreis). There's an
Einkaufspreis column captured but it's hidden on the index. For an
operator using the list to spot pricing/margin issues, surfacing
margin or Einkaufspreis would help.

### F7. Multi-language requirement is rigid for short fields **[Low]**
Variety ("Gijnlim") and origin ("Beelitz, Brandenburg") often won't
need translation. Forcing 3 languages on every field of every product
slows admin entry. Consider letting EN/FR fall back to DE for fields
where the value is the same.

### F8. Product category enum has 6 values across the system **[Med] · DONE (fix/erd-ux-category-taxonomy-batch)**
Admin select shows: Backwaren · Fisch · Fleisch · Gemüse ·
Milchprodukte · Obst (per the customer-side filter chips).
- See **D6** for the marketing/customer-side mismatch and the desired
  end state ("Eier & Milchprodukte" merged etc.). The admin enum needs
  to migrate alongside.

## G. Admin – weekly catalog / inventory

### G1. Two forms with overlapping action URLs on the same page **[Med] · DONE (fix/erd-ux-admin-polish-batch)**
The Wochen-Katalog page contains **two** forms whose `action` URLs both
match `products/weekly`:
- A small "Zu Woche springen" navigation form (GET, action
  `/app/admin/products/weekly`) used to jump to a different week.
- The main save form (POST, action
  `/app/admin/products/weekly/{week}`) used to persist edits.
Tab-Enter on the page hits the navigation form's submit and silently
sends the user to a different week, losing edits in progress.
- **Fix**: rename the navigation form to use a button with
  `formmethod="get"` on the navigation route instead of being a
  separate `<form>`, or make it a real `<a href>` (no form). Or wrap
  the navigation control in a `<details>` so it isn't reachable via
  Tab from inside the table.
- **Bonus**: this is the same root cause as the admin-shell logout
  form (E2). Multiple submit forms on a page = accidental misroute.

### G2. Weekly catalog UI is otherwise excellent ✓
Per-week page, prev/next/picker navigation, "Verteilt: N" + "Verbleibend"
columns showing live consumption against allocations, inline "Neues
Produkt anlegen" affordance, distinct UI for existing vs new product
rows. Strong day-to-day operator UX.

### G3. 5 empty rows pre-rendered — could be 1 with "Zeile hinzufügen" **[Low]**
The form pre-renders 5 empty rows below the populated ones. Most weeks
the operator only adds 0–2 new entries; the empty rows clutter scroll.
- **Fix**: render a single "+ Zeile hinzufügen" button that injects an
  empty row (Alpine.js works fine for this).

### G4. New empty rows lack visual distinction from populated ones **[Low]**
Both look identical except the "VERBLEIBEND" / "LÖSCHEN" cells show
"—". Easy to miss the empty rows below the data. Lighter background
or a "Neu" badge would help.

### G5. Quantity field accepts 0 — does that mean "out of stock" or "drop"? **[Low]**
The validation says `min:0`. Saving an existing row with quantity=0
keeps the stock record at 0 instead of removing it. Operator semantics
unclear — verify whether 0 means "this product is intentionally not in
this week's catalog" (expected) vs "drop the stock row" (also
plausible). Document or add a "Zeile löschen" prompt at 0.

### G6. Shows next-Monday only by default **[Low]**
Default landing is the upcoming Monday's catalog. The operator routinely
needs the *current* week's catalog (e.g., if a substitution is
needed). Easy back-navigation exists ("← 04.05.") but a quick "Diese
Woche / Nächste Woche" toggle at the top would speed things up.

## H. Admin – box setup

### H1. Overview is information-dense and excellent ✓
`/app/admin/boxes` shows 8 weeks at a glance with: WOCHE AB · STATUS ·
AKTIVE ABOS · PROBEBOX · VORHANDENE BOXEN · S · M · L · EIER-ZUSATZ ·
GESAMTWERT. Color status pills (Teilweise / Nicht eingerichtet). Strong
operator-friendly summary.

### H2. Overallocation flagged with red rows + label + disabled save ✓
`/app/admin/boxes/setup/{week}` highlights overallocated stock items in
red (Grünkohl, Kartoffeln, Möhren in our test) with negative "Rest" and
a "Überallokation — siehe rote Zeilen" header. The save button reads
"Boxen speichern · Erst Markierungen klären" and stays disabled while
overallocation remains. Robust safety net.

### H3. Test users leak into "Neu zu verteilen" list **[Med] · DONE (fix/erd-ux-admin-polish-batch)**
The per-customer "Pro Kund:in" section shows
`sumup-test-1@example.test` alongside real customers (Sabine Subscriber,
Trial Tester, Maria Müller, Anna Schmitz). That email is clearly
internal/test data.
- **Fix**: filter `is_test=true` users from the planner unless the
  operator opts in via a "Testkund:innen anzeigen" toggle. Same
  pattern as Testdaten on producers/products.

### H4. Disabled save button uses two-line label inside button **[Low]**
"Boxen speichern" + "Erst Markierungen klären" is rendered as one
button label on two lines. Accessible-tech-wise the screen reader will
read both as a single labelled button. Better: keep the button label
"Boxen speichern" and put the disabled-reason as a `<p>` next to it
(or as a tooltip on hover/focus).

### H5. Per-customer "Eier-Zusatz" sections look right ✓
For each customer with eggs add-on, an Eier-Zusatz subsection shows up
with its own quantities. Aligns with the trial/subscription model.

### H6. Klein/Mittel/Groß "Ziel" / "Max" pricing gates make sense ✓
Each box shows current SUMME against Ziel and Max thresholds, with a
proportional progress bar. Helps operators spot over-/under-priced
boxes at a glance.

### H7. "Neu zu verteilen" / "Bereits eingerichtet" split ✓
Customers split between "to-do" and "done" sections. Reasonable.

### H8. Probebox column on the overview — yet another term **[Low]**
Same glossary issue as E1: this column header is "PROBEBOX" while
the customer flow sometimes calls the same thing "Testbox" or
"Probelieferung". Pick one.

### H9. "Pro Kund:in" rows can break box quantities silently **[Med] · DONE (audit/payment-security)**
Per-customer changes override the size template and can push the
allocated stock above week-stock without the per-customer card showing
a red banner — only the global "Produkte (Wochen-Bestand)" table at
the top reflects it. Operators editing one customer at a time may not
realize they pushed over.
- **Fix**: per-customer card should show its own "Achtung — du
  überschreitest deinen Bestand" hint when its rows alone push
  cumulative > stock.

### H10. Submit button is also rendered as `<button type="submit">` — same accidental-submit risk
Same family as E2/G1: pressing Enter on any quantity input could
submit the form. With this many quantity inputs, the risk is real. Add
`type="button"` to non-submit controls and gate the form submit
behind a click on the actual save button only.

## I. Admin – deliveries & status flow

### I1. Delivery create form fails silently when no Box / no scheduled trial Order **[High] · DONE (audit/payment-security)**
`AdminDeliveryStoreRequest::withValidator` correctly blocks "orphan"
deliveries — a Delivery for a subscriber needs a Box for that ISO week,
and a Delivery for a trial customer needs a paid+scheduled Order on
that exact date. But when the rule rejects, the validator's error
(`admin.deliveries.validation.no_box_or_order`) does not appear
anywhere on the re-rendered page. Admin clicks "Lieferung speichern",
the URL refreshes, no toast/no banner/no inline message — they have
no idea why nothing happened.
- **Fix**: surface the validator's `user_id` error at the top of the
  form (and ideally as a toast). Same goes for any other custom
  rule (`override_requires_base`, `override_already_exists`,
  `canonical_duplicate`).

### I2. Quelle column "Probebox #N" — yet another term **[Low]**
The QUELLE column shows "Probebox #2" for trial-order-backed
deliveries. Adds another spelling to the glossary issue (E1).

### I3. Inline status setter is fast and well-designed ✓
Per-row dropdown with all 5 statuses (Geplant, Unterwegs, Geliefert,
Übersprungen, Fehlgeschlagen) plus a "Speichern" button. Allows
quick state transitions while standing at the warehouse.

### I4. Setting Delivery to "Geliefert" does not update the Order's status **[High] · DONE (audit/payment-security)**
Walked Tobias's trial delivery through Geplant → Unterwegs → Geliefert.
After "delivered":
- `Delivery.status = delivered`, `delivered_at = 2026-05-05 ...`
- BUT `Order.status = scheduled`, `Order.delivered_at = null`

Customer dashboard reads `Order.status`, so Tobias's screen still says
"STATUS: Geplant" while admin marks the delivery as done. Two sources
of truth, customer sees the wrong one. Worse, the order will probably
sit in "Bezahlte Probierboxen, die auf Terminierung warten" reports
forever.
- **Fix**: in `AdminDeliveryController::updateStatus`, if the
  delivery is the canonical one for a trial Order and moves to
  `delivered`, also propagate to the order (status=delivered,
  delivered_at=now). Same for `Subscription` boxes if there's an
  equivalent linkage. Tests required.

### I5. Status dropdown on admin form defaults to first slot 08-12 ✓ but real slot is 12-16 **[Low]**
Same issue as customer-side D2 / C3: the form offers 08:00-12:00 as
a selectable timeslot. Admin form's first option is 08:00-12:00 —
but real fleet only delivers 12:00-16:00.

### I6. "Lieferungen aus Boxen synchronisieren" — name is unclear **[Low]**
Helpful action button at top of the Lieferungen list. Without
hovering or trying it, an admin doesn't know whether it's idempotent,
what it'll create vs update, or what date range it covers.
- **Fix**: add a tooltip "Erstellt fehlende Lieferungen für alle
  vorhandenen Boxen der nächsten 8 Wochen. Bestehende Lieferungen
  werden nicht geändert." (or whatever the actual semantics are).

### I7. "Bulk-Verschieben" toggle is collapsed but the button is on the page  **[Low]**
"Lieferungen eines Tages sammelverschieben (zum Öffnen klicken)"
toggle. Useful when winter delivery is delayed. The label is fine, but
keep the date filter & the move target selector inside this collapsed
block so it doesn't take vertical space when not in use (looks like
it already does — verify on actual interaction).

## J. Subscription payment flow (SumUp + admin tools)

### J1. Order.status and Payment.status can show different things to admin **[Med]**
After bypassing SumUp and marking Tobias's order as paid via a direct DB
update (analogous to a webhook missing/late), the Payments admin page
showed Payment #3 still as **AUSSTEHEND** even while the trial
order's customer dashboard said **Bezahlt**. This is by design (Order
state ≠ Payment state in the data model) but the admin needs a single
"truth view".
- **Fix**: render a "Reconciliation" badge on the payments list when
  Order.status ≠ Payment.status for the same trial. Or expose a
  reconcile-now button on the payment detail page.

### J2. "Validation error" as a reason is opaque **[Low]**
Maria's Abo payment row says FEHLGESCHLAGEN with FEHLERGRUND
"Validation error". Without context, the operator can't tell if it's
a card problem, a 3DS challenge timeout, an amount mismatch, etc.
- **Fix**: store a structured failure reason from SumUp
  (provider_payload-derived) and surface it as a chip with hover
  tooltip ("Card declined", "Insufficient funds", "Authentication
  failed").

### J3. Payment detail page is solid ✓
- Status badge prominent
- "E-Mail erneut senden" with template selector and audit log
- Audit-Verlauf with explicit empty state ("noch keine Audit-Ereignisse
  erfasst")
- Vollständiger Verlauf link (history)

### J4. "Anstehende Abbuchungen (30 Tage)" misses just-onboarded subs **[Med]**
Sabine has an active subscription with a (fake) sumup_token, status
'active', plan 'weekly' — but doesn't appear on the Anstehende
Abbuchungen list. She has `next_charge_date = null`. The list filters
by `next_charge_date in [today, today+30d]`.
- **Fix**: ensure the onboarding-completion path
  (`SubscriptionOnboardingService::finalizeSetup`) sets
  `next_charge_date` to the first billing day. Otherwise the scheduler
  may also miss them.
- **Test**: the seed must mirror reality — the seed user
  `sumup-test-1@example.test` IS in the list because its seed sets
  `next_charge_date`.

### J5. "Abdeckung Abrechnung" matrix is excellent ops UX ✓
Subscription × week color-coded matrix with "Abbuchen" manual trigger.
Helper text correctly disclaims "Abbuchen nur nutzen, wenn du eine
manuelle SumUp-Zahlung für diese Woche auslösen möchtest." Strong.

### J6. "Bestellung #2" / "Probebox #2" disagree across admin pages **[Low]**
On Lieferungen the QUELLE column shows "Probebox #2"; on Zahlungen
the QUELLE column shows "#2" with TYP "Testbestellung"; on the
trial customer-completion screen it was "BESTELLUNG #2". Three
labels for the same record. Add to the glossary cleanup.

### J7. Sidebar "Zahlungen" → page title "Zahlungsversuche" **[Low]**
Sidebar reads "Zahlungen", the page title reads "Zahlungsversuche".
Pick one — "Zahlungen" is the user-friendly term, "Zahlungsversuche"
is more precise (each row is an attempt). Use the same in nav and
heading.

### J8. Customer "Nächstes Abbuchungsdatum" never updated post-onboarding **[Med]**
Sabine's customer-side "Zahlungen" tab still says "Wird nach der
ersten Abbuchung festgelegt" even days after onboarding. (Same
finding as **D4**, surfacing the upstream cause: `next_charge_date`
isn't being set on activation.) Fix once: setting `next_charge_date`
fixes both the admin upcoming-charges and the customer transparency.

### J9. Admin can mark Delivery=Geliefert even when Order has no Box **[High] · DONE (audit/payment-security)**
The admin dashboard shows "Geplante Probierbestellungen ohne
zusammengestellte Box: 1" (Tobias) — yet I just transitioned Tobias's
delivery to Geliefert with no box content. The only thing that was
actually "delivered" is an empty record. The status transition should
be blocked (or at least warn) when the linked Order has no Box / no
contents.
- **Fix**: in `AdminDeliveryController::updateStatus`, when target
  status is `delivered`, require either `Box.items_count > 0` for
  subscription deliveries or `Order.box_id` set for trial deliveries.
  Otherwise show a confirm modal "Diese Lieferung hat keine Box —
  trotzdem als geliefert markieren?".

## K. Cross-cutting (design, copy, data integrity)

These are the structural patterns that surfaced across multiple flows.
Fixing them will help dozens of small symptoms at once.

### K1. Three terms for "Trial Box" — pick one and stick to it **[Med]**
| Surface | Word used |
|---|---|
| Marketing home hero | **Testbox** |
| Trial completion page | **Testbox** |
| Trial customer dashboard card | **Probebox** |
| Admin sidebar nav | **Testbestellungen** |
| Admin overview hinweise | **Probierbestellungen / Probierboxen** |
| Admin payments list | **Testbestellung** |
| Admin deliveries QUELLE | **Probebox #N** |
| Admin payment detail email template | **Probebestellung bestätigt** |
| Customer-side delivery summary | "Probelieferung" (in some pages) |

A single user-facing customer flow (sign-up → completion → dashboard
→ admin email) flips the noun three+ times. Same goes for "Erzeuger"
vs "Produzent" (admin form vs admin list).
- **Action**: write a one-page glossary in `docs/glossary.md` with the
  canonical word per concept. Then grep-and-replace across views,
  emails, enums, sidebar labels, and validation messages.

### K2. Generic `button[type="submit"]` selectors / "Abmelden" form is everywhere **[Med]**
The shared layout renders the user/Abmelden control as a `<form>` with
a submit button. Combined with multi-form pages (onboarding wizard,
weekly catalog, admin pages), this means a generic "submit" selector
or a stray Tab+Enter often hits the logout. We saw it twice during the
review (lost Sabine mid-onboarding, lost admin Aurelia mid-edit).
- **Fix**: render Abmelden as a button on a form scoped to a
  `<aside>` or with `tabindex="-1"`. Or convert to a non-submit-button
  trigger (icon button + JS POST helper). Same for the in-page "Zu
  Woche springen" navigation form.

### K3. Validation copy is in English under a German locale **[High] · DONE (fix/erd-ux-localised-labels-and-validation)**
Default Laravel error messages leak through across many forms (account,
address, email-uniqueness). The locale is `de`, the rest of the page
is German.
- **Fix**: ship a localized `lang/de/validation.php` AND add
  `attributes()` overrides on every FormRequest (`recipient_name` →
  "Empfängername", etc.). This is one PR with a flat blast radius.

### K4. Three different tasks share `delivery_instructions` field **[Med]**
- The address form captures one (saved to `addresses.delivery_instructions`).
- The onboarding schedule step has its own textarea (overwrites
  the address one if filled).
- The Delivery model has `special_instructions` that is never
  populated from either of the above.

The driver, who reads the delivery, never sees what the customer
typed in either form. Sabine's "Klingel rechts neben der Tür, bitte
vor 18 Uhr" never propagated to her Delivery #9.
- **Fix**: pick one source of truth (Address.delivery_instructions
  for permanent + Delivery.special_instructions for one-off
  overrides). Compose them on the Delivery view ("Adresse: …",
  "Diese Lieferung: …"). Stop having two edit surfaces that race.

### K5. Customer-facing delivery slot ≠ what's actually delivered **[High]**
Three places ask the user to pick a slot they cannot have:
- onboarding subscription/schedule (Mo-Fr, 3 timeslots)
- onboarding trial/delivery (3 timeslots)
- admin delivery create form (Mo-Fr, 3 timeslots)

Reality: only Mittwoch 12:00–16:00. The customer view of
`/app/deliveries` then shows the picked-but-not-real slot
(08:00-12:00 in Sabine's case).
- **Fix**: bring the picker in line with the actual delivery zone
  (single Mittwoch 12-16 option for Berlin), or rename the picker
  "Bevorzugter Zeitraum" with a one-line note "Aktueller Liefertag:
  Mittwoch 12-16 Uhr" and never display the user's preference as
  "Zeitfenster" on the delivery card.

### K6. Two database statuses for "the same box of food" **[High]**
For a single physical delivery there are at least three rows that
each carry a status: `Order.status`, `Subscription.status`,
`Delivery.status`, and (for trial) the related `Payment.status`.
None of them are auto-synced across.

Concrete consequences observed:
- Tobias's Order stays `scheduled` while his Delivery is `delivered`.
- Sabine's Subscription stays `active` while she pressed "Skip next
  week" (it actually used `pause_from/until` 2026-05-11..2026-05-17).
- Sabine's Delivery #9 stayed `delivered` after the system
  *deleted* the linked Box during skip — the admin then sees
  "QUELLE: Verwaist".

The customer dashboard reads the Order/Subscription field; the admin
reads Delivery; nobody sees the same truth.
- **Fix**: introduce a single status reducer (e.g. a
  `DeliveryStateService::stateFor(user, week)` that derives the
  customer-shown state from all four sources). Or add explicit
  propagation hooks: when Delivery moves to `delivered`, push to
  Order; when subscription pauses, also cancel orphan Deliveries (or
  flag them).
- **Test**: end-to-end fixture going through onboard → setup → delivered →
  skip should converge on a single "geliefert + nächste Woche
  pausiert" state visible in both views.

### K7. Admin actions silently delete linked records **[High] · DONE (fix/erd-ux-state-coherence)**

Fixed at the cleanup query level: `SubscriptionSkipCleanupService::cleanupWindow`
now `whereDoesntHave('deliveries', ...)` for any non-Scheduled status, so a Box
whose linked Delivery has already moved to InTransit / Delivered / Skipped /
Failed survives the skip even if its own Box.status hasn't propagated yet
(the I4 propagation hop is still on the backlog). Editable+Scheduled boxes
still get cleaned up as before — the change only protects history.
"Skip next week" deletes the existing `Box` for that week (without
warning), even when that Box has been marked `delivered`. This loses
historical data the admin and customer would both want later.
- **Fix**: keep the Box row, mark it `cancelled` / `skipped` instead
  of deleting. History preserved; reports continue to make sense.

### K8. Customer dashboard's "first delivery date" is a calculation, not a record **[High] · DONE (fix/erd-ux-state-coherence)**

The new-subscriber hero now prefers the next concrete `Delivery` row
(`$firstDeliveryCard['delivery']->scheduled_for_date`) over the
cadence-based `firstExpectedDeliveryDate()` calculation. The calculation
remains the fallback for the gap between activation and the first
projected Delivery row.
"Deine erste Box kommt am Mi, 6. Mai" stays on the dashboard
regardless of the actual Delivery row. Once we created Box #9 +
Delivery #9 for 2026-05-13, the hero kept saying 6. Mai. Even after
the customer skipped the week.
- **Fix**: bind the hero text to the *next concrete Delivery row* (or
  the next Box in `Editable`/`Locked` state), not to a derived
  promise.

### K9. `subscription_id` not set on Box → invisible to customer **[High] · DONE (fix/erd-ux-activation-hygiene)**
`WeeklyBoxPlannerService::ensureEmptyBox()` and
`createBoxWithPresetIfMissing()` were creating `Box` rows with
`subscription_id = NULL` even when the box was for a known subscriber.
The customer dashboard query is `whereNotNull('subscription_id')` so
those boxes never showed up.
- Fix: pass `'subscription_id' => $subscription->id` into both
  `Box::create` payloads. Migration 2026_04_23 already backfilled
  pre-existing rows; the bug was new boxes after that date.
- Tests: `WeeklyBoxPlannerSubscriptionLinkTest` (new) +
  extended assertion in `ActivationProjectsFirstBoxTest`.

### K10. Recurring billing fields not set on activation **[Verified false-alarm in production · seed-only fix shipped (fix/erd-ux-activation-hygiene)]**
The original observation came from a manipulated test (the SumUp gateway
was bypassed via tinker so `finalizeSetup` was never called). All three
real activation paths actually do set `next_charge_date` correctly:
- `SubscriptionOnboardingService::finalizeSetup` (subscription wizard)
- `PaymentSetupController::complete` (mandate setup return URL)
- `SubscriptionOnboardController::complete` (legacy onboard return URL)
Each one calls `SubscriptionLifecycleService::computeNextChargeDate()`
before flipping status to active. So in production this isn't a bug.

What WAS broken: `DemoSubscriptionBoxSeeder` left `next_charge_date`
null when it seeded Active subs with a placeholder token. Demo accounts
therefore showed "Wird nach der ersten Abbuchung festgelegt" forever
and were missing from `Anstehende Abbuchungen`. Fixed in the same PR by
calling `computeNextChargeDate()` from the seeder.

### K11. Test data leaks into operator views **[Med]**
On the Box-Setup planner, `sumup-test-1@example.test` is mixed in
with real customers. On the deliveries list, `maria.test@example.com`
and `anna.schmitz.walkthrough@example.test` show up. There's an
`is_test` flag on user/order/box but the planner and deliveries pages
don't apply it as a filter.
- **Fix**: default-filter `is_test=false` in operator views, with a
  "Testdaten anzeigen" toggle for debugging.

### K12. Localised status enums leak as raw English **[Med] · DONE (fix/erd-ux-localised-labels-and-validation)**
Sabine's customer-side delivery card shows "IN TRANSIT" when status =
`in_transit`, while `scheduled` and `delivered` correctly render as
"GEPLANT" / "GELIEFERT". The translation file has gaps.
- **Fix**: audit all enum-to-label mappings (DeliveryStatus,
  SubscriptionStatus, OrderStatus, PaymentStatus, BoxStatus) for DE
  + EN + FR. One PR.

### K13. Admin form failures don't surface validation errors **[High]**
In `AdminDeliveryStoreRequest::withValidator`, custom rules
(`no_box_or_order`, `canonical_duplicate`, `override_requires_base`,
`override_already_exists`) emit error messages but the form re-render
doesn't display them. Admin sees a pristine form, no toast, no
banner — same as success state. We hit this trying to create
Sabine's delivery before her box existed.
- **Fix**: ensure all admin layouts include
  `@error('field')` markers AND render top-level validation summary
  (`$errors->first()`) at the top of the form. Add a "session-flash
  toast" component pattern and use it consistently.

### K14. Eggs add-on charged but not delivered **[High] · DONE (audit/payment-security)**
Sabine's subscription has `eggs_dairy_add_on=true` (will be charged
+4,50€ / delivery). After admin's box edit, her Box has no eggs. No
warning to admin, no warning to customer, no automatic refund logic.
- **Fix**: validate at box-save: if subscription has `eggs_dairy_add_on`
  AND the box has no `eggs_addon` line, either raise a hard error or
  auto-add the egg add-on item. Mirror in
  `EggsUnfulfilledHandler` (it already exists for billing — surface
  it in the box editor too).

### K15. Customer never sees a "skip confirmed" feedback after skip-next **[Med] · DONE (fix/erd-ux-state-coherence)**

The skip-next-week action now flashes a dedicated `subscription-next-week-skipped`
status key plus the actual Monday date. The Abo-overview view renders a
specific banner ("Week of dd.mm.yyyy is paused — no delivery, no charge.")
instead of falling back to the generic "Pause timeframe saved" used by
multi-week pauses.
After Sabine clicked "Nächste Woche überspringen", she landed on the
Aussetzen tab page with no visible toast/banner confirming success.
Worse, her dashboard still shows the now-skipped delivery as her
"Nächste Lieferung". She has to dig in to `Abo > Aussetzen` to see
which weeks are paused.
- **Fix**: post-action toast "Woche vom 11.05. wurde ausgesetzt." +
  bind dashboard "Nächste Lieferung" to the next non-paused delivery.

### K16. Subscription "Aktiv" status doesn't reflect a paused week **[Med] · DONE (fix/erd-ux-state-coherence)**

The dashboard's Abo card now exposes `pause_from` / `pause_until`
explicitly: while the subscription is still Active and a pause window is
scheduled for an upcoming week, it shows "Pause from dd.mm.yyyy"
underneath the status; once "today" enters the window (status flips to
Paused) it shows "Back on dd.mm.yyyy". Two new data-testids
(`dashboard-subscription-pause-pending`, `dashboard-subscription-pause-active`).
Sabine's Abo card on dashboard says "Aktiv · EUR 39,50 / Lieferung"
even when she's paused for the upcoming week. It would be more honest
to show "Aktiv · 1 Woche pausiert · nächste Lieferung 20. Mai".
- **Fix**: composite status copy that includes pause windows.

### K17. Hero/headline copy ignores customer state changes **[Med] · DONE (fix/erd-ux-state-coherence) — partial**

K8 covers the "first box arrives" hero on the new-subscriber dashboard
state, which was the most-cited offender during the review. Other dashboard
strings still derive from per-state classification (DashboardKind enum) and
are stable for the moment; a deeper "single state reducer" rewrite (K6)
remains on the backlog for the broader rebinding pass.
The dashboard hero is a static-ish promise ("Deine erste Box kommt
am Mi, 6. Mai") that doesn't update as state changes (box created,
delivery scheduled, week skipped). It's the most prominent text on
the page, and it can become contradictory to what's below.
- **Fix**: derive the hero text from the same state reducer that
  drives the cards below — one source of truth per dashboard page.


## L. Payment security audit

Investigation triggered by a follow-up question during the trial-pipeline
batch: can a customer pay less than they should, or otherwise tamper with
the payment flow? Findings below — each item is rated by exploit
practicality, not just theoretical surface.

### L1. Server is the source of truth for every charge amount ✓

Every SumUp checkout amount is derived server-side. The client only sends
form fields like `box_size` (validated against `Rule::in(['small','medium',
'large'])`) and `eggs_dairy_add_on` (a boolean). The price is then
recomputed via `BoxPricing::trialOrderTotal()` /
`Subscription::expectedChargeAmount()` / `BoxPricing::subscriptionRecurringTotal()`
using the config in `config/box.php`. Nothing the customer types in their
browser changes the EUR figure on the SumUp checkout.

Verified entry points: `TrialOrderService::createTrialOrder()`,
`SubscriptionRecurringChargeService::initiate()` (called from
`AdminBillingCoverageController`, `ChargeSubscriptions` command,
`RetryFailedPayments` command), `SpargelOrderService::quote()`. All read
from `config/box.php` constants.

### L2. Checkout-id ↔ subscription/order binding is checked on return **[low risk, well covered]**

After a SumUp checkout completes, the customer's browser is redirected to
our controller with `?checkout=…`. An attacker could hand-craft this URL
with someone else's checkout id. The defense:
`PaymentSetupController::complete`,
`SubscriptionOnboardingService::finalizeSetup`,
`SubscriptionOnboardController::complete` and `TrialOrderService::confirmPaidFromProvider`
all assert that the SumUp checkout's `checkoutReference` starts with the
expected `sub-{$subscription->id}-` (or `order-{$order->id}-`) prefix.
That prefix is set when the checkout is created (server-side) and can't
be forged without breaking SumUp's API. Mismatches return early with a
warning logged. ✓

### L3. Webhook double-defense **[low risk, well covered]**

Webhook is at `POST /api/sumup/webhook` and runs the
`sumup.webhook.verify` middleware:
- HMAC-SHA256 signature against `SUMUP_WEBHOOK_SECRET`
- 5-minute Date-header staleness check
- `webhook_idempotency_keys` table prevents replays of the same
  `event_type:checkout_id` pair
- The handler then **re-fetches** the checkout from SumUp via
  `getCheckout()` before marking anything paid — even a forged "PAID"
  payload that somehow bypassed the signature check would be caught
  here.
- The local `Payment.status` is asserted to still be
  `STATUS_PENDING` before any state change. ✓

### L4. Webhook secret bypass when SUMUP_WEBHOOK_SECRET is empty **[Med — config risk, not exploit]**

[`app/Http/Middleware/VerifySumUpWebhookSignature.php:20-22`](app/Http/Middleware/VerifySumUpWebhookSignature.php) silently
skips signature verification when the env var is unset:
```php
if (trim($secret) === '') {
    return $next($request);
}
```
The defense-in-depth at L3 (re-fetch + idempotency) means this isn't a
live exploit, but it does mean a misconfigured deployment can't tell
itself "we have webhook signing on" with confidence. The middleware
should hard-fail (log + 503 / 401) when no secret is configured AND the
env is `production`, while keeping the silent-pass for local/test where
SumUp can't reach you anyway.
- **Fix**: in `production`, return 503 if `SUMUP_WEBHOOK_SECRET` is
  empty. Add a startup health check / `php artisan about` line that
  surfaces "webhook signing: enabled/disabled" so the operator notices.

### L5. Webhook handler does not assert SumUp-side amount equals local Payment amount **[Low — defense in depth gap]**

When `SumUpWebhookHandler` re-fetches a PAID checkout, it doesn't
compare the SumUp `amount` against the local `Payment.amount` before
calling `markPaid()`. If a future SumUp API regression, partial-refund
race, or backend bug were to land a checkout PAID at a different EUR
figure than we expected, the local row would still flip to paid.
Real-world risk is small (we don't expose amount to the client; SumUp
collects what we asked for), but a one-line guard would close the
last theoretical drift.
- **Fix**: in `SumUpWebhookHandler::handle`, after the `getCheckout()`
  call, refuse to mark paid when
  `data_get($checkout, 'amount')` !== `$payment->amount`. Log
  the mismatch and 409 the webhook (SumUp will retry).

### L6. Spargel return URLs leak PII to anyone with the order id **[High — practical, no auth required]**

`/spargel/{spargelOrder}/return` is publicly readable for any spargel
order. Spargel order ids are sequential auto-increment integers
(`$table->id()` in the migration). The view exposes:
- Customer email (`spargel.return_paid_body` includes `:email`)
- Total amount paid
- Delivery address (when `fulfillment_mode === 'delivery'`) including
  street, postcode, city
- Order items (green/white asparagus quantities) and dates

An attacker who notices the sequential-id pattern can iterate
`/spargel/1/return`, `/spargel/2/return`, … and scrape every spargel
customer's email + address. No auth required.

The trial-shop is the project's only fully-anonymous purchase channel,
so this is the highest-risk PII exposure surface.
- **Fix options** (least invasive first):
  1. Switch the route to a [signed URL](https://laravel.com/docs/routing#signed-urls)
     — `URL::signedRoute('public.spargel.return', ['spargelOrder' => $id])`
     when sending the customer to `/return`, then verify with `signed`
     middleware. The customer's email link / browser redirect both work
     with signed URLs.
  2. Require a per-order session token: stash a random token in the
     session at order-creation time, embed it in the URL, verify on
     /return. Survives a refresh, breaks across browsers.
  3. Switch spargel order ids to UUIDs (most thorough but biggest
     change).
- **File**: `app/Http/Controllers/SpargelOrderController.php::return`,
  `routes/web.php`.

### L7. CSRF coverage ✓

Web routes are CSRF-protected by default; the only exclusion is
`payment-diagnostics` (a no-op telemetry beacon that doesn't mutate
payment state). The webhook lives under `routes/api.php` (not subject to
web CSRF) and is protected by signature verification instead. ✓

### L8. IDOR coverage on payment-relevant endpoints ✓

Customer-facing routes that load a model from the URL all check
`$model->user_id === $request->user()->id`:
- `Onboarding\Trial\PaymentStepController::complete` /
  `confirmation`
- `DeliveryController::show` / `gift` / `storeGift`
- `BoxController::show`
Spargel is the only exception (anonymous channel — see L6).

### L9. Token-reuse / mandate handling ✓

SumUp tokens are stored on the `mandates` table (not on
`subscriptions.sumup_token` directly any more — that's a fallback for
legacy rows). `SumUpMandateManager` ties a mandate to a single
customer + subscription. Recurring charges use
`createRecurringCheckout(amount, customerId, …)` — a stolen token
without the matching `sumup_customer_id` won't authorise.
