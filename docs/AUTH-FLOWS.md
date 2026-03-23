# Auth flows and redirects (ERD-173)

This document describes how Laravel Breeze auth behaves in Erdgut. Route names live under the `/auth` prefix (see `routes/auth.php`).

## Global middleware defaults

Configured in `bootstrap/app.php`:

- **Guests** requesting protected routes are redirected to **`/auth/login`**.
- **Authenticated** users hitting guest-only auth pages (e.g. login/register) are redirected to **`/app/dashboard`** (via framework default).

## Registration

1. `GET /auth/register` — registration form (guest layout).
2. `POST /auth/register` — validates name, email, password (`Password::defaults()` → **minimum 8 characters** unless customized).
3. On success: user is **logged in** and redirected to **`route('app.dashboard')`** (`RegisteredUserController`).
4. If email verification is enabled: accessing `/app/*` routes that require `verified` middleware sends the user to **`/auth/verify-email`** until the email is verified.

## Login

1. `GET /auth/login` — login form.
2. `POST /auth/login` — on success, redirect to **`redirect()->intended(route('app.dashboard'))`** (`AuthenticatedSessionController`), so a saved “intended” URL is honoured when the user was forced to log in from a deep link.

## Logout

- `POST /auth/logout` — session ended; redirect is defined in `AuthenticatedSessionController` (typically the site root `/`).

## Email verification

1. Unverified users see **`GET /auth/verify-email`** (verification notice).
2. `POST /auth/email/verification-notification` — resends the verification mail; flash **`status = verification-link-sent`** on success.
3. `GET /auth/verify-email/{id}/{hash}` (signed) — marks email verified; redirect to dashboard (with `?verified=1` where configured).

## Password reset

1. `GET /auth/forgot-password` — request reset link.
2. `POST /auth/forgot-password` — sends reset notification; session **status** message on success (see Laravel `Password::RESET_LINK_SENT` translation).
3. `GET /auth/reset-password/{token}` — set new password form.
4. `POST /auth/reset-password` — on success, redirect to **`route('auth.login')`**.

## Confirm password

- `GET /auth/confirm-password` — used before sensitive actions; on success, **`redirect()->intended(route('app.dashboard'))`** (`ConfirmablePasswordController`).

## Update password (signed-in)

- **Not** on the guest layout: `PUT /auth/password` from the profile page (`profile/partials/update-password-form.blade.php`) updates the password while logged in.

## Related README

See the main [README](../README.md) **Design / UX** section for links to broader UX baseline docs.
