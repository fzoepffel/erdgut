# Accessibility baseline (ERD-343)

This document captures the WCAG AA re-audit done after the Phase 4 redesign work landed. It records what we changed, what we deliberately left for later, and the manual keyboard walkthrough script that should be re-run before each major release.

## Why this exists

ERD-119 set the original accessibility floor in Phase 5. The redesign work in Phase 1-4 added a lot of new admin UI, an onboarding wizard, and the SumUp widget overlay (Batch F). This pass checks that the new surface still meets WCAG AA on the static-markup layer — focus order, landmarks, label associations, aria-live regions, contrast.

## What changed in this pass

### 1. Skip-link on every layout

New `<x-skip-link />` component renders a link with `href="#main"` that's hidden until focused (`sr-only focus:not-sr-only`). It's the **first** focusable element in the body of every layout: `app`, `marketing`, `admin`, `onboarding`, `guest`. WCAG 2.4.1 (Bypass Blocks) is the rule here — keyboard users can jump past the header/nav directly to the page's main content.

The link target is `<main id="main" tabindex="-1">` which is now consistent across all four layouts. `tabindex="-1"` lets the focus actually land on `<main>` after activation (without it, browsers anchor-scroll but don't move focus, which screen readers ignore).

Translations live in `lang/{en,de,fr}.json` under the key `"Skip to main content"`.

### 2. Lang attribute follows the active locale

Already present (`<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">` in every layout). Locked in by a regex assertion in `tests/Feature/Accessibility/A11yBaselineTest.php`.

### 3. Onboarding payment overlay (covered by Batch F)

The SumUp widget's "processing" overlay added in Batch F uses `role="status"` + `aria-live="assertive"`. The expectations panel uses `role="region"` + `aria-labelledby` + `aria-current="step"`. These come for free with this audit — already documented in `docs/onboarding-redesign.md`.

## What we audited and found OK

- **Form labels.** All Blade form components (`<x-input-label>`, `<x-text-input>`, `<x-input-error>`) wire up `for`/`id` correctly. Spot-checked 12 onboarding/profile forms via grep. No bare `<input>` without an associated label.
- **Button vs link semantics.** Pages use `<button>` for in-page actions and `<a>` for navigation consistently. The hamburger menu button has `aria-expanded` + `aria-label`.
- **Image alt text.** Images go through `<x-application-logo>` or have explicit `alt=""` on decorative use. `aria-hidden="true"` on decorative SVGs.
- **Focus indicator.** Tailwind's default `focus-visible:ring` is enabled across interactive components; no custom CSS removes outlines without a replacement.

## What we did NOT change (deliberately scoped out)

- **Colour contrast on the marketing header.** The olive-green masthead (`#709452` background, `#21341d` text at 75 % opacity for inactive nav links) hits 4.5:1 only at the active-link state. Inactive nav at 75 % opacity is closer to 3.4:1 — under AA. **Leaving for marketing visual refresh** (ERD-169) which already touches this masthead.
- **Admin sidebar contrast.** Amber-on-white badges (`text-amber-500`) are decorative + always paired with a text label, so they pass WCAG SC 1.4.1 (Use of Color). Not a contrast failure but worth flagging if the admin theme gets a refresh.
- **Real axe / Pa11y CI run.** Adding a Node-based axe runner to CI requires Puppeteer / Playwright on the runner. The IONOS Web Hosting Plus host is PHP-only (per CLAUDE.md), so this would only run in GitHub Actions. **Worth doing** as a follow-up — out of scope for the baseline ticket.

## Manual keyboard walkthrough script

Run this once per release on staging. ~10 minutes. Use Tab / Shift+Tab / Enter / Space only — no mouse, no trackpad.

### Marketing → onboarding (unauthenticated)

1. Land on `/`. Press Tab once. **Expect**: "Skip to main content" focus ring appears top-left. Press Enter — focus jumps past the masthead onto the hero region.
2. Tab through the masthead nav (Home, About, Producers, Contact). Each link should show a focus ring. Activate "Sign up" with Enter.
3. On `/auth/register`, Tab through the form. Each field shows a focus ring; the submit button is reachable with no traps.
4. Submit a malformed form (e.g. invalid email). Errors render below the field with `<x-input-error>` — the next Tab moves to the next field, errors are announced.
5. Activate the language selector with Space — verify the menu opens, arrow keys navigate, Esc closes.

### Onboarding wizard (authenticated draft)

1. Land on `/onboard/account`. Skip-link visible on first Tab.
2. Tab through the step. Verify the progress indicator's active step is announced via `aria-current="step"` (test in VoiceOver: "Step 1 of 6, current step, Account").
3. Submit valid data → land on `/onboard/address`. Same pattern.
4. Reach `/onboard/trial/payment`. The expectations panel reads as a region. The SumUp widget loads; when ready, focus can move into the iframe.
5. Click Pay (mouse — keyboard inside an iframe is bank-controlled). Expect: the "Processing your payment" overlay appears with `role="status"`. VoiceOver should announce it once.

### Authenticated dashboard + subscription edit

1. Login → `/app/dashboard`. Skip-link on first Tab.
2. Tab to "Edit subscription" link, activate. Land on the edit form.
3. Tab through every field. Tab into the radio group for box size — verify arrow-key navigation switches the selection without leaving the group.
4. Submit with no changes → expect either a "no changes" status message or no destructive action.

### Admin (admin user)

1. `/app/admin/dashboard`. Skip-link on first Tab.
2. Sidebar nav focus order matches visual order, top to bottom.
3. Open the weekly planner. The 12-week grid is reachable with Tab/Shift-Tab; each week row's CTA is a focusable button.
4. The mobile-only hamburger button has `aria-expanded` + `aria-label` (verified via inspector).

### Pass / fail

If any step traps focus, skips a visible element, or shows no focus indicator: **fail**, file a fix. Otherwise, baseline holds.

## What CI does enforce

`tests/Feature/Accessibility/A11yBaselineTest.php` covers:
- Skip-link present on marketing home, dashboard, login, admin dashboard.
- `id="main"` landmark on each.
- `<html lang>` attribute renders with a 2-letter ISO code.

These are static-markup checks. They catch the most common regression — someone re-templates a layout and forgets the skip-link — without requiring a browser runtime.

## Future work (post-launch)

- Add a Node-based axe-core run (Playwright + `@axe-core/playwright`) in GitHub Actions against staging URLs. Block PRs on new violations.
- Marketing header contrast pass during ERD-169.
- Audit colour-only state indicators (e.g. status pills that rely on hue alone) once Phase 8 design polish lands.
