# 钱潮 Money Tide — Project Handoff

Living handoff for any agent (Codex / Claude Code) continuing this project.
Keep this updated at the end of each week.

- **Repo:** plantain-tech/money-tide-cn (PUBLIC, GitHub Actions unmetered)
- **Stack:** PHP 8.x, MariaDB on Hostinger, GitHub Actions → FTPS deploy
- **Production:** https://moneytidecn.avanturadeals.com
- **Health probe:** `/health.php` (release marker per milestone)
- **Admin login:** `/admin/login` (owner has credentials)
- **Current release marker:** `week-6-done`
- **Last updated:** end of Week 6

---

## Non-negotiable workflow rules

- Owner does **not** run PHP locally. Never ask for local `php -l` output.
  GitHub Actions runs `php -l` on every file as the deploy gate.
- Deploy to production first (push to `main`), then test against production.
  Wait ~5–8 min for the FTPS deploy, then poll `/health.php`.
- Bump the release marker in `public/health.php` on every feature push so the
  deploy can be detected. Pure bugfixes may skip the bump and verify by behavior.
- All schema changes ship as `CREATE TABLE IF NOT EXISTS` + idempotent
  `ALTER TABLE` inside `try/catch`. **Never** require a manual SQL import.
- Nothing AI-related may auto-publish. Publishing an article is only
  `/admin/articles/{id}/status`; broadcasting a newsletter is only
  `/admin/newsletter/{id}/send`. Both are manual, human-gated.

---

## Project status (end of Week 6)

| Week | Scope | Status |
|------|-------|--------|
| 1 | MVP | live |
| 2 | Homepage, reading, newsletter growth, editorial workflow, AI agency, analytics, launch | live + tested |
| 3 | Roles, media, newsletter send, research desk, reader accounts, tags/SEO, diagnostics | live + tested |
| 4 | Account polish, Google OAuth, subscriber prefs, newsletter publishing | live + tested |
| 5 | AI newsroom: bots, intake, draft queue, fact/risk, rewrite/localization, newsletter bot, progress modal | live + tested |
| 6 | Social distribution, AI captions, WeChat export, share cards, 60秒看懂, share analytics, QA | live + tested |

---

## Week 6 — what shipped

### Days 1–3 · Social distribution
- `social_posts` table (`article_id`+`channel` PK; status draft/ready/posted/archived; content, hashtags, note, generated_by, timestamps).
- 5 channels in `social_channels()`: `wechat`, `xiaohongshu`, `linkedin`, `twitter`, `email_short` (each with label, char limit, hint, tone).
- `/admin/social` cross-article index (status tabs + channel filter + search).
- `/admin/articles/{id}/social` per-article workspace (per-channel card, live char counter, status dropdown, copy-to-clipboard).
- `generate_social_caption(articleId, channel)` — 1 AI call per channel.
- `/admin/articles/{id}/wechat-export` — inline-styled WeChat HTML with copy-HTML / copy-text / source toggle / subscribe CTA.

### Days 4–6 · Visual + skim + tracking
- `src/share_cards.php` — SVG share cards (no GD). 3 templates (headline/summary/quote), per-category palette, CJK word-wrap. Public route `/share-card/{slug}/{type}.svg` (image/svg+xml, cacheable). `/admin/articles/{id}/share-cards` preview + `social_image_path` override. `article_social_image_url()`: override → hero → category fallback → generated card.
- `src/short_format.php` — `article_short_format` table (summary, bullets, key_number, why_it_matters, risk_note). AI generate + manual edit + copy + delete. Public 60秒看懂 card on `/article/{slug}#short-format`.
- `src/social_analytics.php` — reads `analytics_events` + `subscribers`. `share_utm_url()` adds utm_source / utm_medium=social / utm_campaign. Article share buttons + bottom share prompt all UTM-tagged + channel-tracked. `/admin/social-analytics`: shares, channels, social referred views, referral signups, top shared, subscribe sources.

### Day 7 · QA
- `week_six_qa_checklist()` + `week_seven_backlog()` in `monetization.php`.
- `/admin/week6-checklist` (live smoke banner, Definition of Done, QA list, Week 7 backlog).
- Mobile/spacing polish: max-width guards (no horizontal scroll), stat grids 2-up on phones, full-width action buttons on mobile, 36px copy-button tap targets.

### Bugfix (`e4523c1`)
The original `/admin/ai-drafts/new` generator used strict `json_decode`; gemma sometimes wraps JSON in markdown fences → "Ollama Cloud returned invalid JSON". Now routed through `robust_json_decode()` (strips ```` ```json ```` fences, extracts `{...}`). **All** AI paths (draft, rewrite, research brief, newsletter, social caption, 60秒看懂) now use this resilient parser.

---

## Key architecture notes

- **AI provider:** Ollama Cloud, model `gemma4:31b-cloud`. Daily call cap is `ai.daily_limit`, set via GitHub secret `AI_DAILY_LIMIT` (currently ~21/day). Counted in `ai_usage_logs`.
- **`robust_json_decode()`** lives in `src/ai_sources.php` — use it for any new AI JSON parsing. **`call_simple_json_api()`** in `src/newsletter_ai.php` is the lightweight helper for `{key: value}` style AI calls.
- **Branded confirm modal:** add `data-confirm` (+ optional `-sub`/`-variant`/`-title`/`-confirm`) to any button; JS in `app.js` handles it. Never use `window.confirm()`.
- **Non-dismissible AI progress modal:** add `data-ai-progress` (+ `-title`/`-phases` JSON/`-foot`) to any form that triggers an AI call.
- **`/admin/smoke?format=json`** is the canonical health check (currently 21/21). Add a check there for every new subsystem.
- **`/admin/diagnostics`** lists all table row counts; add new tables to its `$tables` array and to the `diagnostics_export_csv` allowlist.
- **Reader session** (`reader_session()`) is separate from **admin session** (`current_user()`).

---

## Open items / owner actions (not blocking dev)

1. Email is still `EMAIL_PROVIDER=log` (records, doesn't send). To go live: set `EMAIL_PROVIDER` (resend/brevo/mailgun) + `EMAIL_API_KEY` + `EMAIL_FROM_ADDRESS` secrets, verify sender DNS.
2. Google OAuth app is in Testing mode — owner must Publish it (or add tester emails) before public reader signups via Google.
3. `UNSUBSCRIBE_SECRET` secret unset (falls back to a built-in default); set a long random value before heavy email sending.
4. First request right after admin login sometimes 302s to `/admin/login` (Hostinger PHPSESSID settling) — harmless, retry works. Not a bug.

---

## Week 7 backlog (also live at `/admin/week6-checklist`)

- Share-card SVG → PNG rasterizer (for platforms needing bitmaps)
- Social post scheduling (`scheduled_at` + cron reminder, still manual send)
- Newsletter auto-broadcast cron on `newsletter_issues.scheduled_at`
- Personalized digests by `reader_preference_topics`
- Public full-text search (title/dek/brief/body)
- Public RSS/Atom feed (`/feed/all.xml`, `/feed/category/{slug}.xml`)
- Reader comments + admin moderation queue
- Editorial calendar (articles + newsletters on a month view)
- Real email delivery go-live
- Google OAuth public launch
- Payments + hard paywall (Stripe/Paddle)

---

## Next

Start Week 7 from the owner's plan (or the backlog above if none given).
Build → push to `main` → wait for deploy → smoke test against production
with both an admin and a normal (logged-out) browser session → report.
