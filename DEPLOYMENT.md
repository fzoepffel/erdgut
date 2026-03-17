# Deployment Guide

This project uses GitHub Actions to deploy to IONOS Web Hosting Plus via FTP/FTPS.

## Branch Strategy

| Branch   | Deploys to              | Trigger        |
|----------|-------------------------|----------------|
| `staging`| staging.erd-gut.de      | Push to staging|
| `main`   | erd-gut.de (production) | Push to main   |

## Setup

### 1. Get FTP credentials from IONOS

1. Log in to [IONOS](https://www.ionos.com) → **Hosting** → **Manage**
2. Find **FTP access** or **FTP accounts**
3. Note:
   - **Server**: e.g. `ftp.erd-gut.de` or the host shown
   - **Username**: your FTP user
   - **Password**: your FTP password

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
| `FTP_SERVER`           | FTP server hostname                  | `ftp.erd-gut.de` |
| `FTP_USERNAME`        | FTP username                         | `u123456789`   |
| `FTP_PASSWORD`        | FTP password                         | (your password)|
| `STAGING_SERVER_DIR`  | Remote folder for staging (trailing `/`) | `staging/`  |
| `PRODUCTION_SERVER_DIR`| Remote folder for production (trailing `/`) | `/` or `www/` |

**Note:** If IONOS uses a different root (e.g. `www/` or `htdocs/`), set `PRODUCTION_SERVER_DIR` accordingly. Check your IONOS File Manager for the correct path.

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

**FTP connection fails**
- Try `protocol: ftp` instead of `ftps` in the workflow (edit `.github/workflows/deploy-*.yml`)
- Check IONOS uses port 21 (FTP) or 990 (FTPS implicit)

**Wrong files deployed**
- Check `server-dir` matches your IONOS folder structure
- Use IONOS File Manager to see the exact path

**Deployment runs but site doesn't update**
- Clear browser cache
- Check the deployed path in IONOS File Manager
