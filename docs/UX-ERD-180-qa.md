# Account settings and profile UX (ERD-180)

**Ticket:** ERD-180 — Account profile and access UX  
**Routes:** `account.profile.edit`, `account.profile.update`, `account.profile.destroy`, `account.users.show`  
**Purpose:** Manual QA after grouped account settings layout, email-change copy, danger zone, and nav label.

---

## Preconditions

- Authenticated user (verified and unverified email) to test verification banner and resend link.

---

## Account settings (`account/profile`)

1. **Page title** — Header shows “Account settings”; account menu uses the same label (no separate “Profile” in nav).
2. **Sections** — Three landmarks: **Profile and contact**, **Sign-in security**, **Danger zone**; headings are visible and sections are keyboard-navigable.
3. **Email helper** — Copy explains that changing email resets verification and re-verification is required for protected actions.
4. **Unverified email** — Amber panel with resend link; link uses emerald focus ring (not indigo).
5. **Password** — Form still posts to `password.update` (PUT); success flash “Saved.” appears as before.
6. **Danger zone** — Rose-tinted card; intro line about password confirmation; **Delete account** opens modal with title, description, password field (`aria-labelledby` / `aria-describedby`), Cancel and confirm.
7. **Delete flow** — Wrong password shows errors in `userDeletion` bag; correct password logs out and redirects home.

---

## `account.users.show` (non-customer)

- **Not** linked from navigation or dashboard. GET `account/users/{id}` returns **204** for self, **403** for others — authorization probe for policy tests only. No user-facing page to QA.

---

## German locale

- With `APP_LOCALE=de`, spot-check new strings (Kontoeinstellungen, Gefahrenbereich, E-Mail-Hinweis).

---

## Automated coverage

`tests/Feature/ProfileTest.php`, `tests/Feature/Auth/PasswordUpdateTest.php`, `tests/Feature/Security/UserOwnershipAuthorizationTest.php`.
