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
- Run migrations manually on the server: `php artisan migrate --force`
