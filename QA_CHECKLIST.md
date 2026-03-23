# Erdgut MVP — Manual QA Checklist

**Environment:** Staging  
**Date:** ___________  
**Tester:** ___________

---

## 0. Marketing homepage (`/`)

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 0.1 | Guest: homepage loads; hero shows **Get started** (register) and **Log in**; links reach correct routes | | | |
| 0.2 | Narrow viewport (~375px): headline and primary CTA readable and tappable without horizontal scroll | | | |
| 0.3 | Authenticated user: hero primary **Go to dashboard** and secondary **Start subscription** present and links work | | | |

## 0b. Marketing About (`/about`)

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 0b.1 | About loads; hero shows **Get started**, **Contact us**, **Meet our producers**; links resolve | | | |
| 0b.2 | FAQ accordion opens/closes (three items); **Write to us** links to contact | | | |
| 0b.3 | Narrow viewport (~375px): story and values readable; closing CTAs tappable | | | |

## 0c. Marketing producers (`/producers`)

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 0c.1 | Page loads; toolbar **Region** + **Search** + **Apply**; cards show name and region | | | |
| 0c.2 | Filter and search narrow the grid; **Clear filters** resets list; zero-match message when appropriate | | | |
| 0c.3 | Guest: **Sign up to shop extras** on cards; authenticated: **View in shop** opens catalog filtered by producer | | | |
| 0c.4 | Empty database vs filtered-empty messages are distinct; mobile single-column grid | | | |

## 0d. Marketing contact (`/contact`)

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 0d.1 | Form loads; **Your details** and **Your message** groups; privacy note and throttle hint visible | | | |
| 0d.2 | Submit with invalid email or empty message shows inline errors; fields show error styling | | | |
| 0d.3 | Valid submit shows **Message received** banner with next steps; button shows sending/disabled state during submit | | | |
| 0d.4 | Mobile: email keyboard (`inputmode`), comfortable tap targets on submit | | | |

## 1. Authentication

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 1.1 | Register new account with valid data | | | |
| 1.2 | Register rejects duplicate email | | | |
| 1.3 | Register rejects weak/mismatched password | | | |
| 1.4 | Email verification notice shown after registration | | | |
| 1.5 | Verification email received and link works | | | |
| 1.6 | Unverified user cannot access /app/dashboard | | | |
| 1.7 | Login with valid credentials | | | |
| 1.8 | Login rejects invalid credentials | | | |
| 1.9 | Logout clears session, redirects to / | | | |
| 1.10 | Forgot password sends reset email | | | |
| 1.11 | Password reset link works and updates password | | | |
| 1.12 | Guest accessing /app/* is redirected to login | | | |
| 1.13 | Authenticated user accessing /auth/login is redirected to dashboard | | | |

## 2. Subscription

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 2.1 | Create subscription — select plan (weekly/biweekly) | | | |
| 2.2 | Create subscription — select box size (small/medium/large) | | | |
| 2.3 | View subscription overview page | | | |
| 2.4 | Edit subscription — change plan | | | |
| 2.5 | Edit subscription — change box size | | | |
| 2.6 | Pause subscription — valid date range | | | |
| 2.7 | Pause subscription — rejects pause > 12 weeks | | | |
| 2.8 | Pause subscription — rejects past start date | | | |
| 2.9 | Resume paused subscription | | | |
| 2.10 | Cancel subscription with reason | | | |
| 2.11 | Cancelled subscription cannot be paused/resumed | | | |

## 3. Deliveries

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 3.1 | View deliveries list | | | |
| 3.2 | View delivery detail page | | | |
| 3.3 | Skip a future scheduled delivery | | | |
| 3.4 | Cannot skip past or already-skipped delivery | | | |
| 3.5 | Postpone a delivery | | | |
| 3.6 | Gift a delivery — valid recipient data | | | |
| 3.7 | Weekly override (one-time address/timeslot change) | | | |
| 3.8 | Override values shown instead of base delivery values | | | |
| 3.9 | Update delivery preferences (default address, timeslot) | | | |
| 3.10 | Delivery preferences saved and applied to new deliveries | | | |

## 4. Box

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 4.1 | View current week box with items | | | |
| 4.2 | View upcoming boxes list | | | |
| 4.3 | Add item to editable box | | | |
| 4.4 | Update item quantity in editable box | | | |
| 4.5 | Remove item from editable box | | | |
| 4.6 | Cannot modify locked box | | | |
| 4.7 | Modification deadline enforced (cannot edit after deadline) | | | |
| 4.8 | Box shows estimated value | | | |

## 5. Payments

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 5.1 | Setup payment card (SumUp sandbox) | | | |
| 5.2 | Card widget loads without CSP errors | | | |
| 5.3 | Payment setup completion redirects correctly | | | |
| 5.4 | Update payment card | | | |
| 5.5 | Subscription shows payment method status | | | |
| 5.6 | Recurring charge creates pending payment (via command) | | | |
| 5.7 | Webhook updates payment to paid | | | |
| 5.8 | Webhook updates payment to failed | | | |
| 5.9 | Failed payment is retried (via command) | | | |
| 5.10 | Payment success notification received | | | |
| 5.11 | Payment failure notification received | | | |

## 6. Navigation & UI

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 6.1 | Primary nav: Dashboard, Subscription, Deliveries, Shop extras visible | | | |
| 6.2 | Active nav link highlighted correctly | | | |
| 6.3 | Dropdown: Delivery preferences, Profile, Log out | | | |
| 6.4 | Mobile hamburger menu opens/closes | | | |
| 6.5 | Mobile menu contains same links as desktop | | | |
| 6.6 | Marketing pages accessible (Home, About, Producers, Contact) | | | |
| 6.7 | Contact form submits successfully | | | |

## 7. Error Pages

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 7.1 | 404 page shown for non-existent URL | | | |
| 7.2 | 403 page shown for unauthorized access | | | |
| 7.3 | 500 page renders (trigger via debug route or config) | | | |
| 7.4 | 503 page renders during maintenance mode | | | |
| 7.5 | Error pages use branded layout with German copy | | | |
| 7.6 | Error pages have working CTA links | | | |

## 8. Security

| # | Test case | Pass | Fail | Notes |
|---|-----------|------|------|-------|
| 8.1 | Response includes X-Frame-Options: DENY | | | |
| 8.2 | Response includes X-Content-Type-Options: nosniff | | | |
| 8.3 | Response includes Referrer-Policy header | | | |
| 8.4 | Response includes Permissions-Policy header | | | |
| 8.5 | CSP meta tag present in app layout | | | |
| 8.6 | CSP meta tag present in marketing layout | | | |
| 8.7 | SumUp payment widget works under CSP restrictions | | | |
| 8.8 | HTTPS enforced in production/staging (HTTP redirects) | | | |
| 8.9 | Rate limiting: contact form returns 429 after 5 rapid submits | | | |
| 8.10 | Rate limiting: payment setup returns 429 after 10 rapid requests | | | |
| 8.11 | Health endpoint does not expose environment or DB details | | | |
| 8.12 | Session cookies marked as secure and httpOnly | | | |

---

## Summary

| Section | Total | Pass | Fail |
|---------|-------|------|------|
| Authentication | 13 | | |
| Subscription | 11 | | |
| Deliveries | 10 | | |
| Box | 8 | | |
| Payments | 11 | | |
| Navigation & UI | 7 | | |
| Error Pages | 6 | | |
| Security | 12 | | |
| **Total** | **78** | | |

**Blocking defects found:** ___________  
**Sign-off:** ___________
