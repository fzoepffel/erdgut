# UX QA — ERD-181 (Admin catalog operations)

Manual checks for admin dashboard, producer/product list UX, and related forms after ERD-181.

## Scope delivered

- **Dashboard (`app/admin`):** Intro copy, emerald primary/secondary actions, linked cards to producer and product indexes.
- **Products index:** GET toolbar — search `q` (product name or producer name), optional filters — category (enum), catalog published (live/draft), shop availability. Paginated list preserves query string. Emerald success flash, red error flash, validation errors on bad query params (redirect back). Scroll-safe table with screen-reader caption, column headers with `scope="col"`, optional row thumbnail when an image exists. Delete opens **accessible modal** (Alpine `x-modal`); confirm submits existing DELETE route.
- **Producers index:** GET search `q` matches **producer name or region** (documented here). Same flash/error/modal/table patterns as products (without category/published filters).
- **Forms:** Emerald focus/checkbox tokens on admin product and producer forms; **client-side image preview** after choosing a file (server validation remains authoritative).
- **Locale:** German copy for new strings lives in `lang/de.json` (`APP_LOCALE=de`).

## Explicitly deferred (follow-up)

- **Bulk actions:** Multi-select rows, batch publish/delete, and dedicated batch routes are **out of scope** for ERD-181; implement under a later ticket if needed.

## Suggested checks (German locale)

1. As admin, open **Admin** from the app nav — dashboard shows intro and links/cards; primary buttons use emerald.
2. **Producers:** Search by substring of a name and by substring of a region; reset clears results. Delete opens modal; cancel closes; confirm removes row when allowed.
3. **Products:** Combine search with category and published/available filters; pagination keeps filters. Thumbnails appear when products have stored images.
4. **Create/edit product or producer:** Choose an image — preview appears; submit still respects server rules (size/type).
5. Trigger validation: `q` longer than 120 characters or invalid `category` on products index — expect redirect back with error message(s).

## Related docs

- Baseline matrix: [UX-ERD-168-baseline.md](UX-ERD-168-baseline.md) §4.10.
