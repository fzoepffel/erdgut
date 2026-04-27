# Customer-side UX walkthrough — follow-up items

Items deferred during the browser walkthrough that produced PR `feature/onboarding-ux-walkthrough`. Each entry is sized roughly so it can be scoped into its own batch.

## Tone & translation

### Global formal → informal German pronoun pass (~3h)

`lang/de.json` still has **~70 instances** of formal "Sie/Ihr/Ihre/Ihren/Ihrer/Ihnen" outside the pages we walked. The customer-facing strings on the walked pages (onboarding wizard, dashboard, subscription show/edit, cancel/pause modals) are now clean.

Audit command:
```bash
grep -c "\\bSie\\b\\|\\bIhr\\b\\|\\bIhre\\b\\|\\bIhren\\b\\|\\bIhrer\\b\\|\\bIhnen\\b" lang/de.json
```

Approach:
- **Don't** do a blind sed — most instances are in mid-sentence prose where automated replacement breaks grammar (e.g. "Wählen Sie" needs to become "Wähle", not "Wähle du").
- Read each line, rewrite to informal, retain capitalization rules (sentence-start `Du/Dein` only when starting a sentence; otherwise lowercase).
- Brand voice glossary already established by the walked pages: lowercase `du/dein/dich/dir`, capitalize only at sentence start.
- Verify all three locales stay in parity after.

A subagent with a tight glossary and one-string-at-a-time review is the right shape here.

### Brand voice glossary (~30 min)

While doing the pronoun pass, write `docs/brand-voice.md` with:
- Pronoun policy (informal du, lowercase except sentence start)
- Common verb-form replacements (Wählen Sie → Wähle, Geben Sie ein → Gib ein, Klicken Sie → Klicke)
- Tone (warm, editorial, factual; avoid corporate "Bei Fragen wenden Sie sich…")
- Forbidden words (currently: "vegan", "vegetarisch" in customer copy — product policy)

## Architectural

### Form-submission silent failures (~2h investigation, ~1-2h fix)

**Symptom:** in the preview browser session, a few onboarding form POSTs landed at `/` (home/coming-soon) with no error displayed and the user de-authed. Did not reproduce in a real Chrome session. Possibly preview-browser cookie quirk.

But the user-facing surface is shaped wrong regardless: a session expiry mid-form should produce an explicit "Sitzung abgelaufen — bitte erneut anmelden." flash + a clean redirect to `/auth/login` with `intended_url` retained, not a silent bounce.

Add a `Authenticate::redirectTo()` override that returns `/auth/login` always (never `/`), and a `419 Page Expired` handler with a German message + back-button affordance.

### Dashboard architecture pass (~6-8h)

The polish landed (state-aware empty states, trimmed quick actions, contextual link labels) but the dashboard still has structural overlap:

- "At a glance" 3-column panel + "Nächste Lieferungen" / "Box der aktuellen Woche" / "Almanach" 3-column row below say similar things at slightly different fidelities.
- For an **active subscriber with no current delivery yet** (the onboarding-just-finished state), the page is mostly empty cards.
- For a **steady-state subscriber** (deliveries scheduled), the top row is informative but the bottom row largely repeats it.

Worth a redesign session — pick one of:
- (a) Single chronological feed: next delivery first, then "this week's box", then almanach upcoming weeks
- (b) Two-column: left = "what's happening" (next delivery, box, alerts), right = "actions" (skip, pause, payment, address)
- (c) Status-conditional rendering: new subscribers see a "Welcome — your first box ships X" hero; steady-state sees a normal feed

Driven by what users actually need on this page each week. Worth instrumenting first (e.g. log click destinations) before redesigning blind.

## Smaller nice-to-haves

### Cancel-modal default focus (~5 min)

`<x-secondary-button>` "Abo behalten" should be the default-focused element when the modal opens, so a user pressing Enter doesn't accidentally cancel. Currently the destructive `<x-danger-button>` is reachable via a single Tab — defensible — but moving the safe action to default-focus is the safer pattern.

### Schedule-step "PLANEN" label (~5 min)

The progress bar shows step 5 as `PLANEN` (German for "Schedule" via the existing `lang/de.json` lookup). Reads slightly off. "ZEITPLAN" or just "PLAN" might be cleaner. Low priority.

### Onboarding header brand (~1h, optional)

The onboarding pages render `<x-application-logo>` + the wordmark text "Erdgut" + the eyebrow "EIN PLATZ AM TISCH". Three brand elements stacked. Consider dropping the eyebrow on subsequent steps (only show on step 1) or dropping the wordmark text and letting the logo carry the brand.

### Recurring-price heuristic for medium subscribers (~5 min)

On `/onboard/subscription/payment`, the "Following deliveries: from EUR 35.00 each" reads identical to "First box: EUR 35.00" for medium-box subscribers (both are 35). The "from" hedge is correct (eggs add-on can push it higher) but visually redundant. Could detect "first == recurring floor" and collapse to a single row. Low priority.

### Skip-card week range (~10 min, optional)

The skip-tab cards now show `4. Mai` + `KW 19`. Could also show the closing day as a range: `4.–10. Mai` + `KW 19`. Decided against in the walkthrough since it adds visual weight, but worth re-evaluating with a real subscriber.

### Admin button styling consistency (~30 min)

`btn-danger` is now filled red across the app (admin delete, profile delete, cancel sub). Verify nothing in admin looks wrong with the new style — visually scan `admin/producers/index`, `admin/products/index`, `profile/partials/delete-user-form` after the merge.

## Not yet walked

- **Admin operations**: orders, deliveries, boxes, subscriptions, weekly catalog, payments, planner. This is a large surface — likely 1-2 sessions of its own.
- **Profile / settings**: `/account/profile` and friends. Quick — probably 1 session.
- **Public marketing pages**: `/`, `/about`, `/producers`, `/contact`. Currently in coming-soon mode.
- **Email rendering**: the 7 transactional Mailables we shipped in Batch D — render each in browser via `php artisan mailable:show` or staging preview.
- **Mobile viewport**: most of the walkthrough was at desktop width. The wizard and dashboard need a mobile pass.
- **Dark-mode / high-contrast**: not currently supported. Track if needed.
