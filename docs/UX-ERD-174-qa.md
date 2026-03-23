# ERD-174 QA — App shell and dashboard hub

**Ticket:** ERD-174 — App shell navigation and dashboard hub

## Manual checks

1. **5-second scan** — On `/app/dashboard`, without scrolling on a typical phone width, confirm **This week at a glance** shows next delivery (or a clear empty path), box-change context, and subscription status.
2. **Quick actions** — All buttons resolve: Current box, All deliveries, Subscription, Payments; Shop extras only when `features.shop_extras_enabled` is true.
3. **Payments** — Account menu (desktop) and mobile user block include **Payments** linking to payment history.
4. **Payment nudge** — User with an **active** subscription and **no** `sumup_token` sees the amber banner and **Set up payments** → `app.payments.setup`. With a token saved, the banner is absent.
5. **Admin** — Admin user: one **Admin** nav item to `app/admin`; no **Producers** / **Products** in the global app nav. Non-admin: no Admin link or admin URLs in the shell.
6. **Mobile menu** — Hamburger opens/closes the responsive panel; icons swap; links work.
7. **Empty states** — New user (no subscription / no deliveries / no boxes): dashed cards show helpful copy and CTAs (subscribe, preferences, etc.).

## Automated coverage

- `tests/Feature/Dashboard/DashboardPageTest.php`
- `tests/Feature/Dashboard/DashboardHubTest.php`
- `tests/Feature/Admin/AdminAccessTest.php` (admin vs customer dashboard nav)
