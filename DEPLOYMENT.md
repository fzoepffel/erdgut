# Deployment Guide

This project uses GitHub Actions to deploy to IONOS Web Hosting Plus via SFTP.

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

Add these secrets:

| Secret                 | Description                          | Example        |
|------------------------|--------------------------------------|----------------|
| `SFTP_HOST`            | SFTP server hostname                 | `home12345678.1and1-data.host` |
| `SFTP_USERNAME`        | SFTP username (starts with u or p)   | `u123456789`   |
| `SFTP_PASSWORD`        | SFTP password                        | (your password)|
| `STAGING_SERVER_DIR`   | Remote folder for staging           | `staging/`     |
| `PRODUCTION_SERVER_DIR`| Remote folder for production        | `/` or `www/`  |

**Note:** SFTP uses port 22. Check your IONOS File Manager for the correct remote path (e.g. `/`, `www/`, or `htdocs/`).

### 4. Create the staging branch

```bash
git checkout -b staging
git push -u origin staging
```

### 5. Workflow

- **Test changes:** Push to `staging` → deploys to staging.erd-gut.de
- **Go live:** Merge `staging` into `main` (or push to `main`) → deploys to erd-gut.de

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
