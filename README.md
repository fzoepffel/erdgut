# Erdgut

Erdgut is a farm-to-consumer web app for Berlin. Customers can sign up, manage their account, and (in later phases) manage weekly food box subscriptions from local producers.

This README is written for people who are new to development. Follow the steps in order and copy/paste commands exactly.

## Zero-Knowledge Quick Start (10 minutes)

Use this section if you just want to get the app running quickly.

### A) Run locally on your Mac

1. Open **Terminal** on your Mac.
2. Copy/paste this block:

```bash
# Run in: Local Terminal
cd /Users/frederic.zoepffel
git clone https://github.com/fzoepffel/erdgut.git
cd erdgut
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

3. Open `http://localhost:8000` in your browser.

### B) Minimal production/staging server setup

First, get your SSH details from IONOS:

1. Log in to [my.ionos.com](https://my.ionos.com)
2. Go to **Hosting** -> select your contract -> **SFTP & SSH**
3. Copy these 3 values exactly:
   - **Host** (example: `home12345678.1and1-data.host`)
   - **Username** (example: `u123456789`)
   - **Password** (for that SSH/SFTP user)

Then connect from your Mac Terminal:

```bash
# Run in: Local Terminal (this command opens a remote shell on the server)
ssh YOUR_SSH_USERNAME@YOUR_SSH_HOST
```

When asked, type the SSH password (you will not see characters while typing - this is normal), then press Enter.

If login worked, your prompt changes to something like:

```text
u123456789@home12345678:~$
```

That means you are now in the **Server SSH Terminal**.

Now run:

```bash
# Run in: Server SSH Terminal
cd ~/www        # production
# OR
cd ~/staging    # staging
pwd
ls
php artisan migrate --force
php artisan config:clear
```

`pwd` should show `.../www` or `.../staging`.  
`ls` should show Laravel folders like `app`, `config`, `public`, `routes`.

### C) If signup emails do not arrive

In server `.env`, set SMTP values (`MAIL_MAILER=smtp`, etc.), then run:

```bash
# Run in: Server SSH Terminal
php artisan config:clear
php artisan cache:clear
```

Quick email test:

```bash
# Run in: Server SSH Terminal
php artisan tinker
```

At `>>>` prompt:

```php
use Illuminate\Support\Facades\Mail;
Mail::raw('test', fn ($m) => $m->to('your-real-email@example.com')->subject('Mail test'));
```

Type `exit` to leave tinker.

## Design / UX

- **Phase 8 baseline (ERD-168):** competitor notes, design principles, route-to-ticket matrix, and P0 implementation order — see [docs/UX-ERD-168-baseline.md](docs/UX-ERD-168-baseline.md).
- **App shell & dashboard hub (ERD-174):** navigation IA, dashboard scan layout, manual QA — see [docs/UX-ERD-174-qa.md](docs/UX-ERD-174-qa.md).
- **Subscription management UX (ERD-175):** plan selection, overview sections, lifecycle modals, manual QA — see [docs/UX-ERD-175-qa.md](docs/UX-ERD-175-qa.md).
- **Deliveries & preferences UX (ERD-176):** weekly timeline hub, skip/postpone/gift, default settings vs one-off overrides, manual QA — see [docs/UX-ERD-176-qa.md](docs/UX-ERD-176-qa.md).
- **About page hero image (optional):** add `public/images/about-hero.jpg` to show a photo in the `/about` hero on larger screens (see comment in `resources/views/marketing/about.blade.php`).
- **Auth redirects and flows (ERD-173):** login, registration, verification, and password reset behaviour — see [docs/AUTH-FLOWS.md](docs/AUTH-FLOWS.md).

## What this project uses

- **Laravel 11 (PHP)**: backend framework, routing, auth, database access.
- **Blade + Tailwind CSS**: server-rendered pages and styling.
- **Alpine.js**: simple frontend interactivity.
- **Vite**: builds frontend assets (CSS/JS).
- **MySQL/MariaDB**: application database (staging and production on IONOS).
- **GitHub Actions**: automatic deploy to staging and production.

## Environment overview

- **Local**: your computer for development and tests.
- **Staging**: `https://staging.erd-gut.de` for testing before go-live.
- **Production**: `https://erd-gut.de` live customer site.

Important: staging and production each have their own `.env`, `APP_KEY`, and database credentials.

## Command locations (very important)

Many issues happen because commands are run in the wrong place. Use this map:

- **Local Terminal**: Terminal app on your Mac (for development).
- **Server SSH Terminal**: terminal after you log in to IONOS with `ssh`.
- **Inside Tinker**: interactive Laravel prompt started by `php artisan tinker`.

Quick visual cue:
- If prompt looks like `frederic@... erdgut %` -> you are **local**
- If prompt looks like `u123...@home...:~$` -> you are **on server**

### How to open a local terminal on Mac

1. Press `Cmd + Space`
2. Type `Terminal`
3. Press Enter

### Always verify where you are before running commands

Run this first:

```bash
pwd
```

For local project commands, the output should end with:

```text
/Users/frederic.zoepffel/erdgut
```

If not, move there first:

```bash
cd /Users/frederic.zoepffel/erdgut
```

## Requirements

- PHP 8.3
- Composer
- Node.js 20+
- MySQL 8/MariaDB (or SQLite for local quick setup)

## First-time local setup

### 1) Clone and install dependencies

```bash
# Run in: Local Terminal
git clone https://github.com/fzoepffel/erdgut.git
cd erdgut
composer install
npm install
```

### 2) Create local environment file

```bash
# Run in: Local Terminal (inside project folder)
cp .env.example .env
php artisan key:generate
```

Set at least:
- `APP_URL=http://localhost:8000`
- Database settings (`DB_*`)

### 3) Database setup

For MySQL/MariaDB:
- Fill `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` in `.env`.

For SQLite (local only):

```bash
# Run in: Local Terminal (inside project folder)
touch database/database.sqlite
```

Then in `.env`:
- `DB_CONNECTION=sqlite`
- `DB_DATABASE=database/database.sqlite`

Run migrations:

```bash
# Run in: Local Terminal (inside project folder)
php artisan migrate
```

### 4) Build frontend assets

```bash
# Run in: Local Terminal (inside project folder)
npm run build
```

For frontend hot reload in development:

```bash
# Run in: Local Terminal (inside project folder)
npm run dev
```

### 5) Start local app

```bash
# Run in: Local Terminal (inside project folder)
php artisan serve
```

Open `http://localhost:8000`.

## Current implemented features

- Laravel project bootstrap and deployment workflows
- PHP 8.3 target + coding standards (Pint)
- Auth scaffolding via Laravel Breeze:
  - register
  - login/logout
  - password reset
  - email verification flow
- Authenticated app layout and navigation
- Public marketing layout and homepage

## Production and staging operations

### Logging defaults for IONOS

The app is configured for rotating file logs so disk usage stays predictable on shared hosting.

Recommended `.env` values (already reflected in `.env.example`):

- `LOG_CHANNEL=ionos`
- `LOG_IONOS_STACK=daily,critical_daily`
- `LOG_LEVEL=info`
- `LOG_DAILY_DAYS=30`
- `LOG_CRITICAL_LEVEL=warning`
- `LOG_CRITICAL_DAYS=60`
- `LOG_DEPRECATIONS_CHANNEL=daily`

Log files on server:

- `storage/logs/laravel-YYYY-MM-DD.log` (general app logs)
- `storage/logs/critical-YYYY-MM-DD.log` (warning+ signals for faster triage)

Useful server checks:

```bash
# Run in: Server SSH Terminal
cd ~/www        # or: cd ~/staging
ls -lah storage/logs
tail -n 100 storage/logs/laravel.log
```

### Server requirements (IONOS)

1. Document root must point to Laravel `public/` directory:
   - Production: `www/public`
   - Staging: `staging/public`
2. `.env` must exist on server app root (`www/.env`, `staging/.env`).
3. `APP_KEY` must be set.
4. Correct `DB_*` credentials must be configured.
5. Run migrations on each environment when needed:

```bash
# Run in: Server SSH Terminal (not local)
php artisan migrate --force
```

### Exact SSH flow for server commands

Use these steps any time you need to run server commands:

```bash
# Run in: Local Terminal
ssh YOUR_SSH_USERNAME@YOUR_SSH_HOST
```

If login fails with `Permission denied`, re-check Host/Username/Password in IONOS SFTP & SSH.

Then, on the server (prompt should look like `u...@home...:~$`):

```bash
# Run in: Server SSH Terminal
cd ~/www        # production
# OR
cd ~/staging    # staging
pwd
ls
```

You should see Laravel folders like `app`, `config`, `public`, `routes`.

Now run the command:

```bash
# Run in: Server SSH Terminal
php artisan migrate --force
```

### Deploy flow

- Push to `staging` branch -> deploys staging
- Push to `main` branch -> deploys production
- Deploy only runs after the `CI` workflow succeeds (lint + tests)
- Deploy workflow now runs `php artisan migrate --force` automatically after upload/extract

Detailed instructions: see [DEPLOYMENT.md](DEPLOYMENT.md).

### CI checks (before deploy)

Every push/PR runs a `CI` workflow with:

1. `composer lint` (code style check)
2. `composer test` (automated tests)

If CI fails, deployment is blocked until CI is green.

**Where to check CI results in GitHub**

1. Open the repo on GitHub (e.g. `https://github.com/fzoepffel/erdgut`)
2. Click the **Actions** tab
3. Click the latest workflow run (e.g. "CI" or "Deploy to Staging")
4. Green check = passed; red X = failed (click to see which step failed)

## Mail setup (verification and reset emails)

If emails are not being sent, check `.env`:

- `MAIL_MAILER=smtp` (not `log` if you want real emails)
- `MAIL_SCHEME=smtp`
- `MAIL_HOST=smtp.ionos.de`
- `MAIL_PORT=587`
- `MAIL_USERNAME=<mailbox>`
- `MAIL_PASSWORD=<mailbox-password>`
- `MAIL_FROM_ADDRESS=<sender-address>`

Where to edit this on server:
- production: `~/www/.env`
- staging: `~/staging/.env`

Quick editor command (server terminal):

```bash
nano .env
```

Save in nano: `Ctrl + O`, Enter, then `Ctrl + X`.

After changing mail settings:

```bash
# Run in: Server SSH Terminal
php artisan config:clear
php artisan cache:clear
```

### Mail test command (where people often get stuck)

Step 1:

```bash
# Run in: Server SSH Terminal, inside app folder (~/www or ~/staging)
cd ~/www    # or: cd ~/staging
php artisan tinker
```

Step 2 (only after you see `>>>` prompt):

```php
use Illuminate\Support\Facades\Mail;
Mail::raw('test', fn ($m) => $m->to('your-real-email@example.com')->subject('Mail test'));
```

Step 3:

```php
exit
```

If you see `UnsupportedSchemeException`, check `MAIL_SCHEME` is `smtp` or `smtps` (not `tls`).

## Common commands

| Command | Description |
|---------|-------------|
| `composer test` | Run full automated test suite |
| `composer test:feature` | Run feature tests only |
| `composer test:unit` | Run unit tests only |
| `composer lint` | Check PHP style with Pint |
| `composer format` | Auto-fix PHP style |
| `php artisan test` | Run test suite |
| `php artisan migrate` | Run local migrations |
| `php artisan migrate --force` | Run migrations on server |
| `php artisan config:clear` | Clear cached config |
| `php artisan optimize:clear` | Clear all optimization caches |
| `npm run build` | Build production assets |
| `npm run dev` | Start Vite dev server |

## Troubleshooting

### "No application encryption key has been specified"
- `APP_KEY` is missing. Set it in server `.env`.

### App looks unstyled or different than expected
- Check domain document root points to `public/`.
- Confirm `public/build` assets exist after deploy.

### Login works but emails do not arrive
- `MAIL_MAILER` likely set to `log`.
- Switch to SMTP settings and clear config cache.

### DB errors after deploy
- Check `DB_*` values in server `.env`.
- Check deploy workflow step `Run post-deploy migrations on server`.
- If needed, run recovery manually: `php artisan migrate --force`.

## Security and safety rules

- Never commit `.env` to git.
- Use different `APP_KEY` for local, staging, and production.
- Use separate database credentials for staging and production.
- Keep secrets only in server `.env` and GitHub Secrets.
- The app now fails fast in `staging` and `production` if `APP_KEY` is missing or `CRON_KEY` is left as the default placeholder.

## Glossary (quick plain-language terms)

- **Framework**: a structured toolkit to build apps (Laravel).
- **Route**: URL path mapped to app logic.
- **Controller**: PHP class handling request logic.
- **Migration**: versioned database schema change.
- **CI/CD**: automated build/test/deploy pipeline.
- **Staging**: pre-production test environment.
- **Production**: live environment for real users.

## Next roadmap area

Next major work items after current foundation are route organization, authorization rules, and core domain models (subscriptions, deliveries, boxes, products, payments) as listed in `erdgut-mvp-tickets.csv`.
