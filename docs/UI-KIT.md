# UI kit (ERD-182)

Engineering reference for shared Blade components, Tailwind patterns, and UI states across **marketing**, **auth** (`guest` layout), and **app** (`app` layout). Full WCAG audit is tracked under **ERD-119**; new work should still meet readable contrast and visible focus.

## Buttons

| Use case | Implementation |
|----------|------------------|
| Primary submit / main CTA | `<x-primary-button>` — emerald fill, `text-sm font-semibold`, `focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2` ([`resources/views/components/primary-button.blade.php`](../resources/views/components/primary-button.blade.php)) |
| Secondary / cancel in modals | `<x-secondary-button>` — white + border, emerald focus ring ([`secondary-button.blade.php`](../resources/views/components/secondary-button.blade.php)) |
| Destructive (delete, irreversible) | `<x-danger-button>` — red/rose semantics; confirm in a modal or explicit copy ([`danger-button.blade.php`](../resources/views/components/danger-button.blade.php)) |

**Prefer these components** over copy-pasting long `class` strings for **new** buttons. Some journeys keep **inline** CTAs for deliberate visual hierarchy (e.g. gray-900 “Edit subscription” on subscription overview, purple gift actions on deliveries); document the reason in the PR if adding new ones.

## Page shell

Typical authenticated content:

- Outer: `py-8 sm:py-12`
- Width: `max-w-4xl` (focused flows) or `max-w-7xl` (dashboard-style)
- Cards: `rounded-lg border border-gray-100 bg-white shadow-sm` (or `sm:rounded-lg` where legacy)

Reference: [`resources/views/subscriptions/show.blade.php`](../resources/views/subscriptions/show.blade.php), [`resources/views/shop/index.blade.php`](../resources/views/shop/index.blade.php).

## Empty states

Use `<x-empty-state>` for block-level “nothing here” messaging with optional description and action ([`empty-state.blade.php`](../resources/views/components/empty-state.blade.php)).

- **`variant`:** `inset` (dashed panel inside a card, gray background), `card` (centered dashed border, white), `card-muted` (centered, muted background; optional icon slot).
- **`tone`:** `neutral` (default) or `amber` (filtered / no-results emphasis on marketing).
- Stable hook for tests: `data-testid="empty-state"`.

## Loading

- **Inline actions:** Disable control and swap label (e.g. Alpine `Adding…` on shop product card — [`shop/product-card.blade.php`](../resources/views/components/shop/product-card.blade.php)).
- **Skeletons:** No shared component required; use Tailwind `animate-pulse` on neutral blocks, e.g. `h-4 w-3/4 rounded bg-gray-200`. Keep layout from shifting when content loads.

## Errors, success, “toasts”

Laravel **session flash** is the standard pattern (no separate toast library):

- Success / info: `bg-emerald-50`, `text-emerald-800` (or domain-specific amber for warnings).
- Errors: `bg-red-50`, `text-red-800`.
- Payment-specific copy: [`resources/views/payments/partials/session-status.blade.php`](../resources/views/payments/partials/session-status.blade.php).

## Status badges

`<x-status-badge :value="..." />` — semantic colors for delivery / subscription / payment states ([`status-badge.blade.php`](../resources/views/components/status-badge.blade.php)).

## Modals

`<x-modal name="..." focusable>` — Alpine-driven; pair with `x-on:click="$dispatch('open-modal', 'name')"`. See profile delete flow. Deeper `role="dialog"` / `aria-modal` improvements belong to **ERD-119** unless low-risk.

## Forms

- Use `<x-input-label>`, `<x-text-input>`, `<x-input-error>` from Breeze.
- Focus rings on custom controls: **`focus:border-emerald-600 focus:ring-emerald-600`** (not legacy indigo).

## Further reading

- Phase 8 principles and route matrix: [`docs/UX-ERD-168-baseline.md`](UX-ERD-168-baseline.md)
- Manual QA (empty / loading / flash / keyboard smoke): [`docs/UX-ERD-182-qa.md`](UX-ERD-182-qa.md)
