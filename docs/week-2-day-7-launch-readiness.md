# Week 2 Day 7 Launch Readiness

Shipped scope:

- Expanded `/admin/launch-checklist` from the first-week MVP gate into a Week 2 launch gate.
- Expanded `/admin/qa` to include analytics, AI version history, and AI fact-check support tables.
- Added safe article cleanup for draft and archived articles only.
- Added `POST /admin/articles/{id}/delete`.
- Updated the production health marker to `week-2-day-7-launch-readiness`.

Manual production smoke test:

1. Open `/health.php` and confirm the release marker.
2. Open homepage, latest, one category, one article, subscribe page, sitemap, and robots.
3. Log in to admin and open `/admin/qa`.
4. Open `/admin/launch-checklist`.
5. In `/admin/articles`, confirm draft or archived rows show the delete action.
6. Confirm published rows do not show the delete action.
7. Do not delete published content directly; archive first if it must be removed from public view.
