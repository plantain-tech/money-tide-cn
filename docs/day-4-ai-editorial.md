# Day 4 AI Editorial Layer

Day 4 adds the first AI-assisted editorial workflow.

## Routes

```text
/admin/ai-drafts
/admin/ai-drafts/new
/admin/ai-drafts/{id}
```

## Setup

Day 5 switched the preferred provider to Ollama Cloud. Add these GitHub Actions secrets:

```text
AI_PROVIDER=ollama_cloud
OLLAMA_API_KEY
OLLAMA_MODEL=gemma4:31b-cloud
```

Optional:

```text
AI_DAILY_LIMIT=10
```

After adding the secret, push any commit to regenerate production `config.php`.

## Workflow

1. Log in at `/admin/login`.
2. Open AI Drafts.
3. Click Generate Draft.
4. Choose a section bot.
5. Add at least one source link.
6. Generate the draft.
7. Review source notes and risk notes.
8. Convert to CMS article draft.
9. Edit in the CMS.
10. Publish manually.

## Editorial Safety Rules

AI drafts are never published directly.

Editors must verify:

- source links
- numbers and dates
- names and company facts
- financial risk wording
- investment-advice disclaimers

The generated article draft includes an internal reminder that must be removed or rewritten before publishing.

## Provider Note

The implementation supports Ollama Cloud first and keeps OpenAI as an optional fallback provider.
