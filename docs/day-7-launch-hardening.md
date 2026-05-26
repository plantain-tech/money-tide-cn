# Day 7 Launch Hardening

Day 7 closes the first-week MVP.

## Added

1. `/admin/launch-checklist`
2. Improved `/admin/qa` status badges
3. Security headers in `.htaccess`
4. Admin `X-Robots-Tag: noindex, nofollow`
5. Subscriber CSV UTF-8 BOM for Excel
6. Cleaner 404 page
7. Analytics placeholders:
   - `GA_MEASUREMENT_ID`
   - `PLAUSIBLE_DOMAIN`

## Final Smoke Test

Check:

```text
/
/latest
/subscribe
/sitemap.xml
/robots.txt
/article/chinese-brands-global-pricing
/admin
/admin/qa
/admin/launch-checklist
/admin/articles
/admin/ai-drafts
/admin/subscribers
```

## Launch Gate

The first-week MVP is ready when `/admin/launch-checklist` shows all items passing.
