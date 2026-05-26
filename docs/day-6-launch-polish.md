# Day 6 Launch Polish

Day 6 adds public launch readiness and SEO fundamentals.

## Public SEO

Added:

1. Canonical URLs.
2. Meta descriptions.
3. Open Graph tags.
4. Twitter card tags.
5. Theme color.
6. JSON-LD organization schema.
7. NewsArticle schema on article pages.
8. Default share image.

## Dynamic Discovery

Routes:

```text
/sitemap.xml
/robots.txt
```

The sitemap now includes public pages, categories, and published articles from the database.

## Production QA

Route:

```text
/admin/qa
```

Checks:

1. Database connection.
2. Categories available.
3. Published articles available.
4. AI provider configured.
5. Subscriber table reachable.

## Verification

After deploy, verify:

```text
/health.php
/sitemap.xml
/robots.txt
/admin/qa
```
