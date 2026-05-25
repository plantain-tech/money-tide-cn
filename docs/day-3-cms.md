# Day 3 CMS

Day 3 turns Money Tide into a working publishing platform.

## Admin Routes

```text
/admin
/admin/articles
/admin/articles/new
/admin/articles/{id}/edit
/admin/categories
```

## Editorial Flow

1. Log in at `/admin/login`.
2. Open Articles.
3. Create a draft or published article.
4. Required fields:
   - category
   - title
   - dek
   - 一句话看懂
   - 为什么重要
   - body
5. Set status to `published` to make it public.
6. Confirm the article appears on:
   - homepage
   - latest page
   - category page
   - article URL

## Launch Articles

There are two ways to add starter content:

1. Use the dashboard button `启动文章`.
2. Import `database/day3_seed_articles.sql` in phpMyAdmin.

The dashboard button is protected by admin login and creates 3 launch-style articles.

## Notes

The CMS is intentionally simple. Day 4 can add AI draft generation, source links, editor notes, image upload, and a review queue.
