# Repository agents guide

Conventions for AI coding assistants and contributors working in this repo.

## Documentation (`docs/`)

### Style

- Follow the Laravel docs pattern: concept first, code immediately.
- No emojis in prose or section headers.
- ASCII diagrams are fine where they convey structure (architecture, pipelines, sharding).
- Cross-links use relative `.md` paths: `[Search](search.md)`. No `/docs/` prefix, no absolute `sigmie.com/docs/...` URLs.
- Callouts use `> **Note:**` and `> **Warning:**`. Do not reintroduce custom `@info` / `@danger` blocks.
- Torchlight annotations: `[tl! highlight]` only on the single line each section is teaching. `[tl! add]` / `[tl! remove]` is allowed in analysis/tokenizer/filter pages where before/after diffs are the lesson.
- Headings: H1 = page title, H2 = sections, H3 sparingly. Avoid H4+.
- Skip trailing "Best Practices" / "Performance Tips" bullet lists. Fold useful tips inline.

### Frontmatter

Every page must include:

| Field | Notes |
|-------|-------|
| `title` | Page title. Mirrors the H1. |
| `short_description` | **130–160 characters.** SERP-friendly. See below. |
| `keywords` | Array of search terms relevant to the page. |
| `category` | One of: Getting Started, Configuration, Core Concepts, Features, Text Analysis, Integrations, Advanced Features, Utilities, Reference. |
| `order` | Sort order within the category. |
| `related_pages` | Array of related page slugs (without `.md`). |

### `short_description` rules

The `short_description` becomes the `<meta name="description">` on the docs site and appears as the snippet in Google SERPs.

- **Target 130–160 characters.** Google typically truncates desktop snippets around 160; mobile around 120. Aim for **145–155** to stay comfortably inside both edges.
- **Self-contained.** Must read well in a search result with no other context.
- **Lead with the value.** Active voice, present tense.
- **Include the page's primary keyword.** Usually "Sigmie", "Elasticsearch", or both, plus the feature name.
- **Differentiate from sibling pages.** Don't make every Search-section page sound the same.
- **No filler.** Avoid "Learn how to…", "This page describes…", "In this guide…".

Example:

```yaml
---
title: Search
short_description: Build user-facing Elasticsearch searches with Sigmie — typo tolerance, faceted navigation, highlighting, semantic search, and filter-parser syntax.
keywords: [search, query, filters, sorting, highlighting, typo tolerance]
category: Core Concepts
order: 5
related_pages: [query, document, semantic-search, filter-parser]
---
```

### Verifying description length

```bash
grep -h "^short_description:" docs/*.md | \
  awk -F': ' '{n=length($2); printf "%3d  %s\n", n, $2}' | sort -n
```

Aim for every line to land in the 130–160 column. Anything under 120 or over 165 should be rewritten.

### Canonical references

When unsure about tone or structure, look at `docs/introduction.md` and `docs/search.md`.

## Code style (PHP)

The author's preferences (also in personal `CLAUDE.md`):

- `declare(strict_types=1);` at the top of every file.
- Import classes at the top, never use full namespaces inline.
- Constructor property promotion: `public function __construct(protected Type $property)`.
- Always declare return types.
- Early returns over `else`; avoid deep nesting.
- `match()` over `switch`.
- Inline exceptions: `$foo ?? throw new Exception('…')`.
- Use `rescue()` instead of `try`/`catch` where possible.
- `$arr['key'] ?? null` instead of `isset($arr['key']) ? $arr['key'] : null`.
- Arrow functions for simple callbacks: `fn () => …`.
- Spread operator for array merging: `[...$a, ...$b]` over `array_merge`.

## Commits and PRs

- Author commits and PRs as `@nicoorfi` (Nico Orfanos).
- Don't run formatters (`pint`, `rector`) unless asked.
- Don't run tests unless asked.
- Don't run `npm run build` unless asked.
