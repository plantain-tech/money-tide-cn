# Day 5 Ollama Cloud and Subscribers

Day 5 makes AI generation safer and adds subscriber management.

## Ollama Cloud Setup

Use the model you tested:

```text
gemma4:31b-cloud
```

Add these GitHub Actions secrets:

```text
AI_PROVIDER=ollama_cloud
OLLAMA_API_KEY=your Ollama API key
OLLAMA_MODEL=gemma4:31b-cloud
AI_DAILY_LIMIT=10
```

After adding or changing secrets, push any commit to regenerate production `config.php`.

## AI Safety

The app now:

1. Shows provider/model status in admin.
2. Requires admin login for generation.
3. Requires at least one source link.
4. Logs AI requests in `ai_usage_logs`.
5. Limits AI draft generation per day.
6. Keeps AI output as draft only.
7. Requires manual editor review before publishing.

The `ai_usage_logs` table is created automatically if it does not exist.

## Subscriber Admin

Routes:

```text
/admin/subscribers
/admin/subscribers.csv
```

Features:

1. Search by email.
2. Filter by status and source.
3. See topic preferences.
4. Export CSV for newsletter tools.

## Testing

1. Log in at `/admin/login`.
2. Open `/admin/ai-drafts/new`.
3. Confirm provider shows `Ollama Cloud · gemma4:31b-cloud`.
4. Generate one draft with a valid source link.
5. Review the saved draft.
6. Convert it to article draft.
7. Open `/admin/subscribers`.
8. Export CSV.
