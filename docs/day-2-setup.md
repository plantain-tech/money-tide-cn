# Day 2 Setup: Database, Newsletter, Admin

Day 2 adds the first real application layer:

1. MySQL connection through `config.php`.
2. Real newsletter signup storage.
3. Admin login and dashboard.
4. Database-backed article/category reads with static fallback.
5. Protected database health check.

## Hostinger MySQL

In Hostinger hPanel:

1. Open Websites -> `avanturadeals.com`.
2. Go to Databases -> MySQL Databases.
3. Create a database and user for Money Tide.
4. Import these files in phpMyAdmin:
   - `database/schema.sql`
   - `database/seed.sql`

## Production Config Through GitHub Secrets

Preferred setup: let GitHub Actions generate `config.php` during deployment.

Add these repository secrets:

```text
HOSTINGER_DB_HOST        localhost
HOSTINGER_DB_NAME        your Hostinger database name
HOSTINGER_DB_USER        your Hostinger database username
HOSTINGER_DB_PASSWORD    your Hostinger database password
ADMIN_EMAIL              your editor login email
ADMIN_PASSWORD_HASH      generated PHP password hash
```

After these secrets are added, push any commit to `main`. The deploy workflow will create `config.php` in the subdomain root before uploading files.

## Manual Production Config

Alternative setup: create this file on Hostinger:

```text
/home/u284368723/domains/avanturadeals.com/public_html/moneytidecn/config.php
```

Use this structure:

```php
<?php

declare(strict_types=1);

return [
    'app_env' => 'production',
    'app_url' => 'https://moneytidecn.avanturadeals.com',
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_HOSTINGER_DB_NAME',
        'user' => 'YOUR_HOSTINGER_DB_USER',
        'pass' => 'YOUR_HOSTINGER_DB_PASSWORD',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'email' => 'YOUR_ADMIN_EMAIL',
        'password_hash' => 'YOUR_PASSWORD_HASH',
    ],
];
```

## Admin Password Hash

Generate the password hash on any machine with PHP:

```bash
php -r "echo password_hash('CHANGE_THIS_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
```

Paste the generated hash into `admin.password_hash`.

## Verify

After deployment and config setup:

1. Open `https://moneytidecn.avanturadeals.com/subscribe`.
2. Submit a test email.
3. Confirm it appears in the `subscribers` table.
4. Open `https://moneytidecn.avanturadeals.com/admin/login`.
5. Log in with the admin email and password.
6. Open `https://moneytidecn.avanturadeals.com/admin/db-health`.

If the database is not configured, the public site still works using static fallback content.
