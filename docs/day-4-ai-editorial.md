# Day 4 AI Editorial Layer

Day 4 adds the first AI-assisted editorial workflow.

## Routes

```text
/admin/ai-drafts
/admin/ai-drafts/new
/admin/ai-drafts/{id}
```

## Setup

Add this GitHub Actions secret:

```text
OPENAI_API_KEY
```

Optional:

```text
OPENAI_MODEL=gpt-4.1-mini
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

## OpenAI API Note

The implementation uses the OpenAI Responses API and requests structured JSON output with a JSON schema, so drafts return predictable article fields rather than unstructured prose.
