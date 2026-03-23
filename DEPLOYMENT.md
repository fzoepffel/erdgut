# Deployment Guide

This project uses GitHub Actions to build and deploy a Laravel app to IONOS Web Hosting Plus via SSH (SCP upload + server-side extract).

## Branch Strategy

| Branch   | Deploys to              | Trigger        |
|----------|-------------------------|----------------|
| `staging`| staging.erd-gut.de      | Push to staging|
| `main`   | erd-gut.de (production) | Push to main   |

## Setup

### 1. Enable SSH access in IONOS

1. Log in to [IONOS](https://www.ionos.com) → **Hosting** → **Manage**
2. Go to **SFTP & SSH** and ensure SSH is enabled for your deployment user
3. Note the **Server/Host** (e.g. `home12345678.1and1-data.host`), **Username**, and **Password**

### 2. Configure staging subdomain (required for Laravel)

To use `staging.erd-gut.de`:

1. In IONOS: **Domains** → **erd-gut.de** → **Subdomains**
2. Add subdomain `staging` and set its **document root** to the `public` folder
   - Example: if `STAGING_SERVER_DIR` is `staging`, set document root to `staging/public`
   - Laravel requires the web server to serve from `public/`, not the app root
3. If the document root points to the app root, the server will serve `index.html` (placeholder) instead of Laravel

### 3. Add GitHub Secrets

In your repo: **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

Required secrets:

| Secret                  | Description                                 | Example        |
|-------------------------|---------------------------------------------|----------------|
| `SSH_HOST`              | SSH host                                     | `home12345678.1and1-data.host` |
| `SSH_USERNAME`          | SSH username                                 | `u123456789`   |
| `SSH_PASSWORD`          | SSH password                                 | (your password)|
| `STAGING_SERVER_DIR`    | Remote app directory for staging             | `staging`      |
| `PRODUCTION_SERVER_DIR` | Remote app directory for production          | `www`          |

**Note:** SSH/SFTP uses port 22 by default. Check your IONOS File Manager for the correct folder names (for example `www` and `staging`).

### 4. Create the staging branch

```bash
git checkout -b staging
git push -u origin staging
```

### 5. Workflow

- **Test changes:** Push to `staging` → deploys to staging.erd-gut.de
- **Go live:** Merge `staging` into `main` (or push to `main`) → deploys to erd-gut.de

Each deployment pipeline now does:
1. Setup PHP 8.3 and Composer
2. Setup Node 20
3. `composer install --no-dev --optimize-autoloader`
4. `npm install && npm run build`
5. Create a single `release.tar.gz` archive
6. Upload archive via SCP to `/tmp/` on server
7. Extract archive into target app directory over SSH
8. Run `php artisan migrate --force` over SSH (post-deploy, automatic)

### 6. IONOS + Laravel requirements

Before first production deploy, make sure:

1. Domain/subdomain document root points to Laravel `public/`.
2. `.env` exists on the server (workflows never upload `.env`).
3. `APP_KEY` is set (`php artisan key:generate` once on server if needed).
4. `storage/` and `bootstrap/cache/` are writable by PHP.
5. Database credentials in `.env` are correct.
6. PHP version in IONOS is set to 8.3.
7. Logging env vars are set for rotation on shared hosting:
   - `LOG_CHANNEL=ionos`
   - `LOG_IONOS_STACK=daily,critical_daily`
   - `LOG_DAILY_DAYS=30`
   - `LOG_CRITICAL_DAYS=60`
8. `CRON_KEY` is replaced from the default placeholder in server `.env`.
9. IONOS cron job is configured (see below).

### 7. IONOS cron job (Laravel scheduler)

Laravel’s task scheduler runs via an HTTP trigger because IONOS shared hosting does not support `* * * * * php artisan schedule:run`. Configure a scheduled URL in the IONOS panel:

1. In IONOS: **Hosting** → **Scheduled tasks** (or equivalent)
2. Add a new scheduled task:
   - **URL:** `https://erd-gut.de/cron/run?key=<CRON_KEY>`
   - For staging: `https://staging.erd-gut.de/cron/run?key=<CRON_KEY>`
   - Replace `<CRON_KEY>` with the value from your server `.env` (`CRON_KEY=...`)
   - Use the same secret as in `config('cron.key')`
   - **Interval:** Every 5 minutes
3. The route validates the key; invalid/missing key returns 403
4. After first execution, verify via log entries that scheduled commands ran

### 8. Mail transport (staging/production)

For transactional emails (payment notifications, reminders) on IONOS, configure SMTP in `.env`:

- `MAIL_MAILER=smtp`
- `MAIL_HOST=smtp.ionos.de` (or `smtp.ionos.com` depending on region)
- `MAIL_PORT=587`
- `MAIL_SCHEME=tls`
- `MAIL_USERNAME` / `MAIL_PASSWORD`: IONOS mail credentials for the sending address
- `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME`: Use a verified sending address

Verify with `php artisan tinker` → `Mail::raw('Test', fn ($m) => $m->to('your@email.com'));`

### 9. Encrypted payment data (ERD-113)

When deploying the encrypted casts for `subscriptions.sumup_token`, `subscriptions.sumup_customer_id`, and `payments.provider_payload`:

- The migration `2026_03_23_000001_encrypt_existing_payment_data` encrypts any existing plaintext values. It is safe to run on empty tables.
- Do **not** rotate `APP_KEY` after this migration; encrypted data cannot be decrypted with a different key.

## Admin access (first operator)

Admin UI lives under `/app/admin` and requires `users.is_admin = 1` plus a verified email. There is no public self-service promotion to admin.

1. **Create or register** the user account first (normal registration), or ensure the row exists in `users`.
2. **SSH into the server** (or use IONOS PHP shell / any way to run Artisan in the app root).
3. Run:
   ```bash
   php artisan user:make-admin "operator@your-domain.de"
   ```
   - If the email is unknown, the command exits with an error.
   - If the user is already admin, it succeeds with an “already an admin” message (idempotent).

After this, the user can sign in and open **Admin** / **Products** / **Producers** in the app navigation.

## Catalog audit logs (support / traceability)

Product and producer **writes by admins** are recorded in `catalog_audit_logs` (who, when, action, attribute snapshots). There is no admin UI for browsing logs in MVP; query the database when needed, for example:

```sql
SELECT * FROM catalog_audit_logs
  WHERE auditable_type = 'App\\Models\\Product' AND auditable_id = ?
  ORDER BY created_at DESC;
```

Use `App\\Models\\Producer` for producers. Run `php artisan migrate` after deploy so the table exists.

## Catalog images — products & producers (public storage)

Uploaded catalog images (admin CRUD) are stored on Laravel’s **`public` disk** under `storage/app/public/` and served as `/storage/...` after linking. Product files use `storage/app/public/products/{id}/…`; producer files use `storage/app/public/producers/{id}/…`.

1. **After deploy (once per environment):** from the app root run:
   ```bash
   php artisan storage:link
   ```
   This creates `public/storage` → `storage/app/public`. Without it, image URLs return 404.

2. **Permissions:** ensure `storage/app/public`, `storage`, and `bootstrap/cache` are writable by the PHP user (same as general Laravel requirements).

3. **Persist uploads across deploys:** extracting a new release must **not delete** `storage/app/public` (especially `products/` and `producers/`). If deploys replace the whole tree, symlink or mount persistent storage for `storage/app/public`, or uploads will 404 until admins re-upload. The app hides broken `image_path` rows in the shop when the file is missing.

4. **PHP upload limits (admin uploads):** on IONOS, raise `upload_max_filesize` and `post_max_size` in the host’s PHP settings (e.g. **8M or higher**; admin validation allows up to 5 MB per file) so uploads do not fail. Restart PHP if the panel requires it.

`FILESYSTEM_DISK` in `.env` can remain `local` for private app files; catalog code uses the `public` disk explicitly.

**Legacy producer URLs:** the `producers.image_url` column may still hold external URLs; the app prefers an uploaded file (`image_path`) when set, and falls back to `image_url` for the public producers page.

## Troubleshooting

**`Missing required secrets for production: CRON_KEY` after setting `.env`**

Laravel’s config cache does not load `.env` on boot; `CRON_KEY` must be read via `config('cron.key')` (see `config/cron.php`). If Artisan fails before you can run `config:cache`, delete the cached config and rebuild:

```bash
rm -f bootstrap/cache/config.php
php artisan config:cache
```

**SSH/SCP connection fails**
- Verify `SSH_HOST`, `SSH_USERNAME`, `SSH_PASSWORD` in GitHub Secrets
- Ensure SSH login works in IONOS for that user
- Ensure the user can write to `STAGING_SERVER_DIR` / `PRODUCTION_SERVER_DIR`

**Wrong files deployed**
- Check `STAGING_SERVER_DIR` and `PRODUCTION_SERVER_DIR` match your IONOS folder structure
- Use IONOS File Manager to see the exact path

**Deployment runs but site doesn't update / still shows old placeholder**
- **Document root must point to `public/`**: In IONOS → Domains → Subdomains → staging → set document root to `staging/public` (or `YOUR_APP_DIR/public`)
- Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)
- Check the deployed path in IONOS File Manager: you should see `app/`, `public/`, `config/`, etc.

**Laravel error: no application encryption key has been specified**
- Create/set `APP_KEY` in server `.env`
- If SSH is available, run `php artisan key:generate --force`

**Database changes are not applied**
- Check the deploy workflow logs for the `Run post-deploy migrations on server` step
- If needed for recovery, run manually on server: `php artisan migrate --force`
