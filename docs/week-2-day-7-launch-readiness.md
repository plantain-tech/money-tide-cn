# Week 2 Day 7 Launch Readiness

Shipped scope:

- Expanded `/admin/launch-checklist` from the first-week MVP gate into a Week 2 launch gate.
- Expanded `/admin/qa` to include analytics, AI version history, and AI fact-check support tables.
- Added safe article cleanup for draft and archived articles only.
- Added `POST /admin/articles/{id}/delete`.
- Added mobile polish for category navigation, CJK wrapping, overflow prevention, and narrow-screen spacing.
- Added a GitHub Actions job timeout so FTPS deploys cannot hang forever.
- Updated the production health marker to `week-2-day-7-launch-readiness`.

Manual production smoke test:

1. Open `/health.php` and confirm the release marker.
2. Open homepage, latest, one category, one article, subscribe page, sitemap, and robots.
3. Log in to admin and open `/admin/qa`.
4. Open `/admin/launch-checklist`.
5. In `/admin/articles`, confirm draft or archived rows show the delete action.
6. Confirm published rows do not show the delete action.
7. Do not delete published content directly; archive first if it must be removed from public view.
8. On a mobile viewport, confirm the category nav scrolls horizontally and text does not overflow.
9. Generate one AI draft, convert it to an article draft, preview it, then publish via the workflow.
10. Submit one newsletter subscription from `/subscribe` and confirm it appears in `/admin/subscribers`.

Week 3 backlog:

- Add proper editor roles and writer roles instead of a single admin account.
- Add image upload/selection for article hero images and Open Graph images.
- Add source management and saved source profiles for AI drafting.
- Add article delete audit log if deletion becomes common.
- Add email delivery integration for the actual daily newsletter send.
- Add WeChat/Apple/Google login implementation when OAuth credentials are ready.
- Add real Most Read ranking from internal analytics to replace editorial fallback.
- Add automated browser tests for homepage, article, subscribe, admin login, and publish workflow.
