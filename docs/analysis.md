---
title: Text Analysis
short_description: How Elasticsearch turns text into tokens with char filters, a tokenizer, and token filters, and why Sigmie runs one analyzer on documents and queries.
keywords: [analysis, analyzers, text processing, tokenization]
category: Text Analysis
order: 1
related_pages: [tokenizers, token-filters, char-filters, language, mappings]
---

# Text Analysis

Analysis turns a string into the tokens that Elasticsearch stores and compares. It runs twice: on each text field when you index a document, and on the query string when you search. A query matches a document when both sides produce the same tokens. That is why Sigmie runs one analyzer on both sides.

## An analyzer has three stages

```
input text
   │
   ▼
[Char filters]    change the raw string (strip HTML, map symbols)
   │
   ▼
[Tokenizer]       split the string into tokens
   │
   ▼
[Token filters]   change, add, or remove tokens (lowercase, stopwords)
   │
   ▼
tokens
```

An analyzer has exactly one tokenizer. Char filters and token filters are optional, and an analyzer can have many of each.

| Stage | Works on | Example | Reference |
|-------|----------|---------|-----------|
| Char filters | The whole string, before it is split | Strip HTML tags | [Character Filters](char-filters.md) |
| Tokenizer | The whole string, once | Split on whitespace | [Tokenizers](tokenizers.md) |
| Token filters | Each token, one filter after another | Lowercase every token | [Token Filters](token-filters.md) |

## Follow one string through the pipeline

This index builds an analyzer with one stage of each kind:

```php
$sigmie->newIndex('quotes')
    ->stripHTML()
    ->tokenizeOnWhitespaces()
    ->lowercase()
    ->stopwords(['are', 'for'])
    ->create();
```

Here is what happens to a field value:

```
"<span>Some people are worth melting for</span>"
   │
   ▼ Char filter: strip HTML
"Some people are worth melting for"
   │
   ▼ Tokenizer: whitespace
"Some" "people" "are" "worth" "melting" "for"
   │
   ▼ Token filter: lowercase
"some" "people" "are" "worth" "melting" "for"
   │
   ▼ Token filter: stopwords ("are", "for")
"some" "people" "worth" "melting"
```

Elasticsearch stores the four tokens at the bottom. It does not store the HTML, the capital letters, or the stopwords.

## Queries go through the same analyzer

At search time, the query string takes the same path:

```
"People Worth Melting"
   │
   ▼ no HTML to strip
   ▼ whitespace tokenizer
"People" "Worth" "Melting"
   │
   ▼ lowercase, stopwords
"people" "worth" "melting"
```

All three query tokens exist in the document, so the document matches. If only the document side were lowercased, `People` would never equal `people`, and the same search would find nothing.

## Filter order changes the result

Token filters run in the order you add them. The `stop` filter compares tokens case-sensitively, so a stopword list in lowercase only works after `lowercase()`:

```
lowercase(), then stopwords()        stopwords(), then lowercase()

"For" → "for" → (dropped)            "For" → "For" (kept) → "for"
```

Add normalizing filters such as `lowercase()` and `asciiFolding()` before the filters that compare against a word list.

## Where Sigmie attaches an analyzer

Sigmie builds analyzers at two levels:

```
newIndex('movies')
├─ analyzer "default"            built from the index builder methods
│                                 used by every text field without its own
└─ properties
   ├─ description  → "default"
   └─ email        → "email_field_analyzer"
                      built in withNewAnalyzer(), used only by this field
```

The analysis methods on the index builder, such as `lowercase()` or `tokenizeOnWhitespaces()`, build the analyzer named `default`. Elasticsearch uses `default` for every text field that has no analyzer of its own. With no analysis methods, `default` uses the word boundaries tokenizer and no filters.

`withNewAnalyzer()` on a text property builds an analyzer for that field only. Sigmie names it after the field path with a `_field_analyzer` suffix and uses it for both indexing and search on that field. Some property types, such as `title()`, `email()`, and `path()`, build their own field analyzer. See [Mappings](mappings.md) for per-field analyzers.

`language()` swaps the index builder for one with language-specific filters. See [Languages](language.md).

## Inspect the tokens an analyzer produces

`analyze()` sends text through one of the index's analyzers and returns the tokens. The second argument names the analyzer and defaults to `default`:

```php
$sigmie->index('quotes')->analyze('<b>Worth</b> melting for');
// ["worth", "melting"]

$sigmie->index('contacts')->analyze('nico@sigmie.com', 'email_field_analyzer');
```

Check the tokens before you reindex. A document token that differs from the query token never matches.
