# UX QA — ERD-182 (UI kit and cross-cutting states)

Manual checks after ERD-182: shared patterns, empty states, and consistency with the engineering doc.

## Documentation

- Engineers: [UI-KIT.md](UI-KIT.md) — buttons, page shell, `x-empty-state`, loading/error/flash patterns, deferred a11y scope (**ERD-119**).

## Empty states (`data-testid="empty-state"`)

Verify copy, spacing, and optional actions render correctly:

| Area | Trigger |
|------|--------|
| **Shop index** | Filters/search that match no products; with active filters, primary “Clear all filters” appears. |
| **Dashboard** | No delivery cards; no current week box; no upcoming boxes (as applicable). |
| **Payments history** | No ledger rows yet (icon + title + description). |
| **Marketing producers** | No producers in DB; or filters/search with zero matches (amber tone + “Clear filters”). |

## Loading

- **Shop:** Add-to-box control shows loading/disabled state while submitting (product card).

## Errors / flash

- **Dashboard:** Amber banner when current box missing (if applicable).
- **Payments:** Session status partial after setup/update flows.
- **Subscriptions:** Emerald/amber session strips per state.

## Keyboard / focus (smoke)

1. From app layout: Tab through logo, nav links, profile menu; open profile dropdown and reach items.
2. **Shop:** Tab to filter controls and product actions.
3. **Account delete:** Open delete modal, Tab to password field, Cancel, and danger submit; Escape closes modal.
4. **Payments / subscription** pages: First focusable control in main content receives visible focus ring (emerald or semantic red on destructive).

Full WCAG 2.2 AA audit is **ERD-119**, not this ticket.

## Routes

**No new or changed named routes** — ERD-182 remains §4.11 cross-cutting only ([UX-ERD-168-baseline.md](UX-ERD-168-baseline.md)).
