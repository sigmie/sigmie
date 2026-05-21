---
title: Text Analysis
short_description: How Elasticsearch transforms text at index and query time
keywords: [analysis, analyzers, text processing, tokenization]
category: Text Analysis
order: 1
related_pages: [tokenizers, token-filters, char-filters, language]
---

# Text Analysis

Analysis is how Elasticsearch transforms text into searchable tokens. Every text field is analyzed at index time, and every query string is analyzed the same way at search time. When both sides apply identical transformations, matching becomes a fast set operation.

## The pipeline

Every analyzer has three stages:

```
input text
   │
   ▼
[Char filters]    pre-process raw characters (strip HTML, map symbols)
   │
   ▼
[Tokenizer]       split into tokens
   │
   ▼
[Token filters]   transform tokens (lowercase, remove stopwords, stem)
   │
   ▼
indexed tokens
```

Each stage is optional but tokenization. The tokenizer is the one required component — char filters and token filters are added as needed.

## A worked example

Index this HTML text:

```php
"<span>Some people are worth melting for</span>"
```

With this analyzer:

```
Analyzer
├─ Char filters
│  └─ Strip HTML
├─ Tokenizer
│  └─ Whitespace
└─ Token filters
   ├─ Lowercase
   └─ Stopwords (drop "are", "for")
```

### Step 1: Char filters

The HTML strip removes tags:

```
"<span>Some people are worth melting for</span>"   →   "Some people are worth melting for"
```

### Step 2: Tokenize

Whitespace tokenizer splits on spaces:

```
"Some people are worth melting for"

→ "Some"
→ "people"
→ "are"
→ "worth"
→ "melting"
→ "for"
```

### Step 3: Token filters

Lowercase normalizes case; stopwords drops common words:

```
"Some"    → "some"
"people"  → "people"
"are"     → (dropped)
"worth"   → "worth"
"melting" → "melting"
"for"     → (dropped)
```

The indexed tokens are:

```
"some" "people" "worth" "melting"
```

## Query analysis

A query string goes through the **same** analyzer. Query "Some people worth melting":

```
"Some people worth melting"
   │
   ▼ (no HTML to strip)
   │
   ▼ Whitespace tokenizer
"Some" "people" "worth" "melting"
   │
   ▼ Lowercase + stopwords
"some" "people" "worth" "melting"
```

Now Elasticsearch can match tokens against the index:

```
Query Term    Document 1    Document 2
"some"           ✓             ✓
"people"         ✓             ✓
"worth"                        ✓
"melting"        ✓             ✓
```

Document 2 matches more terms, so it scores higher.

## Configure analysis in Sigmie

Index-level analysis runs on every text field unless a field overrides it:

```php
$sigmie->newIndex('movies')
    ->tokenizeOnWhitespaces()        // tokenizer
    ->lowercase()                    // token filter
    ->trim()                         // token filter
    ->stripHTML()                    // char filter
    ->create();
```

See [Tokenizers](tokenizers.md), [Token Filters](token-filters.md), and [Character Filters](char-filters.md) for every option.

## Per-field analysis

Override analysis on a single field:

```php
use Sigmie\Index\NewAnalyzer;

$props->text('email')
    ->withNewAnalyzer(function (NewAnalyzer $analyzer) {
        $analyzer->tokenizeOnPattern('(@|\.)');
        $analyzer->lowercase();
    });
```

## Test the analyzer

`analyze()` runs text through the index's analyzer and returns the resulting tokens:

```php
$sigmie->index('movies')->analyze('The Matrix Reloaded');
// ["matrix", "reloaded"]
```

Use this to verify a field is being tokenized the way you expect before re-indexing the world.

## Language-specific analysis

English, German, and Greek have purpose-built analyzers with stemmers, stopwords, and normalizers — see [Languages](language.md).

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->language(new English)
    ->englishStemmer()
    ->englishStopwords()
    ->englishLowercase()
    ->create();
```
