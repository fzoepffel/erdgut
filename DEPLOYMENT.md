# Deployment Guide

This project uses GitHub Actions to build and deploy a Laravel app to IONOS Web Hosting Plus via SFTP.

## Branch Strategy

| Branch   | Deploys to              | Trigger        |
|----------|-------------------------|----------------|
| `staging`| staging.erd-gut.de      | Push to staging|
| `main`   | erd-gut.de (production) | Push to main   |

## Setup

### 1. Create SFTP account in IONOS

1. Log in to [IONOS](https://www.ionos.com) → **Hosting** → **Manage**
2. Go to **SFTP & SSH** (or **FTP accounts**)
3. Create a new account:
   - **Type:** SFTP (recommended)
   - **Directory:** `/` for production, or `/staging/` if you want staging in a subfolder
4. Note the **Server/Host** (e.g. `home12345678.1and1-data.host`), **Username**, and **Password**

### 2. Configure staging subdomain (optional)

To use `staging.erd-gut.de`:

1. In IONOS: **Domains** → **erd-gut.de** → **Subdomains**
2. Add subdomain `staging` pointing to folder `/staging/`
3. Or create folder `staging` in your webspace and point the subdomain to it

### 3. Add GitHub Secrets

In your repo: **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

Required secrets:

| Secret                 | Description                          | Example        |
|------------------------|--------------------------------------|----------------|
| `SFTP_HOST`            | SFTP server hostname                 | `home12345678.1and1-data.host` |
| `SFTP_USERNAME`        | SFTP username (starts with u or p)   | `u123456789`   |
| `SFTP_PASSWORD`        | SFTP password                        | (your password)|
| `STAGING_SERVER_DIR`   | Remote folder for staging           | `staging/`     |
| `PRODUCTION_SERVER_DIR`| Remote folder for production        | `/` or `www/`  |

Optional secrets for automatic migrations:

| Secret                    | Description |
|---------------------------|-------------|
| `SSH_HOST`                | SSH hostname if you want migration over SSH |
| `SSH_PORT`                | SSH port (default `22`) |
| `SSH_USERNAME`            | SSH username |
| `SSH_PASSWORD`            | SSH password |
| `STAGING_MIGRATE_URL`     | Protected HTTP URL that runs `php artisan migrate --force` on staging |
| `PRODUCTION_MIGRATE_URL`  | Protected HTTP URL that runs `php artisan migrate --force` on production |

**Note:** SFTP uses port 22. Check your IONOS File Manager for the correct remote path (e.g. `/`, `www/`, or `htdocs/`).

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
5. Upload code and built assets via SFTP
6. Optionally run migrations via SSH or HTTP trigger (if related secrets are configured)

### 6. IONOS + Laravel requirements

Before first production deploy, make sure:

1. Domain/subdomain document root points to Laravel `public/`.
2. `.env` exists on the server (workflows never upload `.env`).
3. `APP_KEY` is set (`php artisan key:generate` once on server if needed).
4. `storage/` and `bootstrap/cache/` are writable by PHP.
5. Database credentials in `.env` are correct.
6. PHP version in IONOS is set to 8.3.

```bash
# Deploy to staging
git checkout staging
git merge main
git push

# After testing, deploy to production
git checkout main
git merge staging
git push
```

## Troubleshooting

**SFTP connection fails**
- Verify host, username, and password in GitHub Secrets
- SFTP uses port 22 (default)
- Check that the SFTP account has access to the target directory

**Wrong files deployed**
- Check `STAGING_SERVER_DIR` and `PRODUCTION_SERVER_DIR` match your IONOS folder structure
- Use IONOS File Manager to see the exact path

**Deployment runs but site doesn't update**
- Clear browser cache
- Check the deployed path in IONOS File Manager

**Laravel error: no application encryption key has been specified**
- Create/set `APP_KEY` in server `.env`
- If SSH is available, run `php artisan key:generate --force`

**Migrations don't run automatically**
- Configure SSH secrets (`SSH_HOST`, `SSH_USERNAME`, `SSH_PASSWORD`) or
- Add a protected migration trigger URL and set `STAGING_MIGRATE_URL` / `PRODUCTION_MIGRATE_URL`
