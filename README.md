# 钱潮 Money Tide

Chinese-language financial news MVP built with PHP and MySQL for Hostinger hosting.

## Local Run

From the project folder:

```powershell
php -S localhost:8080 -t public
```

Open:

```text
http://localhost:8080
```

## Hostinger Deployment

1. Upload the contents of this project to the hosting account.
2. Point the web root to `public` if your Hostinger plan supports it.
3. If the web root must be `public_html`, upload the contents of `public` into `public_html` and keep `src`, `views`, `database`, and config files outside public access where possible.
4. Import `database/schema.sql`.
5. Import `database/seed.sql`.
6. Copy `config.example.php` to `config.php` and fill in production credentials.

## Week 1 MVP

The first launch focuses on public reading and subscription:

1. Homepage.
2. Article page.
3. Category pages.
4. Latest page.
5. Subscribe page.
6. Editorial standards and disclaimer pages.
7. Newsletter capture.
8. Basic admin CMS next.
