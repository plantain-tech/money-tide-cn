# Money Tide Deployment Workflow

This project is designed for a simple production loop:

1. Edit and test locally.
2. Commit changes to Git.
3. Push to GitHub `main`.
4. GitHub Actions deploys to Hostinger automatically.
5. Review the production URL immediately.

## Recommended Hostinger Subdomain Layout

Create a Hostinger subdomain for the first launch.

Current production preview target:

```text
moneytidecn.avanturadeals.com
```

Deploy the app so that only `public/` is web-accessible:

```text
/home/u284368723/domains/avanturadeals.com/public_html/moneytidecn/
  index.php, assets/  <- files from local public/
  src/                <- files from local src/
  views/              <- files from local views/
  config.php          <- production secrets, created manually on Hostinger later
```

Do not put `config.php`, `database/`, or internal docs inside `public_html`.

In Hostinger hPanel:

1. Go to Websites.
2. Open the dashboard for the root domain.
3. Open Subdomains.
4. Create the subdomain, such as `moneytidecn`.
5. Choose a custom folder/document root if Hostinger offers it.
6. Use the new subdomain's `public_html` folder as `HOSTINGER_PUBLIC_DIR`.

## GitHub Secrets

Create these in GitHub:

Repository -> Settings -> Secrets and variables -> Actions -> New repository secret.

```text
HOSTINGER_FTP_SERVER      Hostinger FTP/FTPS hostname
HOSTINGER_FTP_USERNAME    Hostinger FTP username
HOSTINGER_FTP_PASSWORD    Hostinger FTP password
HOSTINGER_PUBLIC_DIR      /
HOSTINGER_APP_DIR         /
PRODUCTION_URL            https://moneytidecn.avanturadeals.com
```

The workflow uses FTPS and deploys:

- `public/` -> `HOSTINGER_PUBLIC_DIR`
- `src/` -> `HOSTINGER_APP_DIR/src/`
- `views/` -> `HOSTINGER_APP_DIR/views/`

## First-Time Setup

Run these commands from `D:\AllAi\Followthemoneycn\FTMC-design` after creating the GitHub repository:

```powershell
git init
git branch -M main
git add .
git commit -m "Initial Money Tide launch scaffold"
git remote add origin https://github.com/YOUR-ACCOUNT/YOUR-REPO.git
git push -u origin main
```

After the first push, open GitHub -> Actions -> Deploy to Hostinger to watch the deployment log.

## Production Config

Create `config.php` on Hostinger beside `src/` and `views/`, not inside `public_html`.

Use `config.example.php` as the template and fill production credentials there.

## Daily Workflow

```powershell
git status
git add .
git commit -m "Describe the update"
git push
```

Every push to `main` deploys production. For risky work, create a feature branch and merge to `main` only when ready.

## Health Check

The deployment workflow verifies:

```text
https://moneytidecn.avanturadeals.com/health.php
```

Expected response:

```json
{"status":"ok","app":"money-tide","checked_at":"..."}
```
