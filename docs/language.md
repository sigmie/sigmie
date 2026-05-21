---
title: Languages
short_description: Language-specific analyzers and token filters
keywords: [languages, multilingual, greek, german, english, stemming, stopwords]
category: Text Analysis
order: 5
related_pages: [analysis, token-filters, tokenizers]
---

# Languages

Sigmie ships purpose-built analyzers for **English**, **German**, and **Greek**. Each language has its own stemmers, stopword lists, lowercase normalizer, and (where appropriate) script-specific normalizers.

To use a language, pass an instance to `language()` on the index builder, then chain the filters you want:

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->language(new English)
    ->englishStemmer()
    ->englishStopwords()
    ->englishLowercase()
    ->create();
```

`language()` returns a builder typed to that language, so the chained methods are language-specific and discoverable.

## English

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->language(new English)
    ->englishStemmer()
    ->englishStopwords()
    ->englishLowercase()
    ->create();
```

### Available filters

| Filter | Purpose |
|--------|---------|
| `englishStemmer()` | Standard English stemmer. |
| `englishPorter2Stemmer()` | Porter2 (Snowball) stemmer. More aggressive than the default. |
| `englishLightStemmer()` | Lighter stemming — keeps more of the original form. |
| `englishLovinsStemmer()` | The Lovins algorithm. |
| `englishMinimalStemmer()` | Minimal stemming for high-precision use cases. |
| `englishPossessiveStemming()` | Strip trailing `'s` and `'`. |
| `englishStopwords()` | Drop English stopwords. |
| `englishLowercase()` | Lowercase tokens. |

Pick **one** stemmer per analyzer — they overlap. Porter2 is the standard choice; the light/minimal variants help when stemming reduces precision too much.

```php
$sigmie->newIndex('articles')
    ->language(new English)
    ->englishPorter2Stemmer()
    ->englishPossessiveStemming()
    ->englishStopwords()
    ->englishLowercase()
    ->create();
```

## German

```php
use Sigmie\Languages\German\German;

$sigmie->newIndex('artikel')
    ->language(new German)
    ->germanNormalize()
    ->germanStemmer()
    ->germanStopwords()
    ->germanLowercase()
    ->create();
```

### Available filters

| Filter | Purpose |
|--------|---------|
| `germanStemmer()` | Default German stemmer. |
| `germanStemmer2()` | Alternate German stemmer (variant 2). |
| `germanLightStemmer()` | Lighter stemming. |
| `germanMinimalStemmer()` | Minimal stemming. |
| `germanNormalize()` | Normalize umlauts and ß. |
| `germanStopwords()` | Drop German stopwords. |
| `germanLowercase()` | Lowercase tokens. |

`germanNormalize()` is usually worth including — it folds `ü→u`, `ö→o`, `ä→a`, `ß→ss`, so queries match regardless of how users type umlauts.

## Greek

```php
use Sigmie\Languages\Greek\Greek;

$sigmie->newIndex('arthra')
    ->language(new Greek)
    ->greekLowercase()
    ->greekStemmer()
    ->greekStopwords()
    ->create();
```

### Available filters

| Filter | Purpose |
|--------|---------|
| `greekStemmer()` | Greek stemmer. |
| `greekStopwords()` | Drop Greek stopwords. |
| `greekLowercase()` | Lowercase Greek tokens (handles σ → ς word-final form). |

`greekLowercase()` does more than ASCII lowercase — it handles the Greek-specific final-sigma form. Use it instead of the generic `lowercase()` for Greek text.

## Multi-language indices

Per-field analysis lets you mix languages in one index. Define one analyzer per language-specific field:

```php
use Sigmie\Index\NewAnalyzer;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\German;

$props->text('description_en')
    ->withNewAnalyzer(function (NewAnalyzer $analyzer) {
        $analyzer->tokenizeOnWordBoundaries();
        // language-specific filters via separate builder
    });
```

For most cases, simpler to keep one index per language and search across them:

```php
$sigmie->newSearch("articles_de,articles_en")
    ->properties($props)
    ->queryString('Tür door')
    ->get();
```

## See also

- [Analysis](analysis.md) — how text becomes searchable.
- [Token Filters](token-filters.md) — generic, language-agnostic filters.
- [Indices](index.md) — index configuration.
