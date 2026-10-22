---
title: Languages
short_description: Sigmie reference for the English, German, and Greek index builders, with their Elasticsearch stemmers, stopword lists, lowercase filters, and normalizer.
keywords: [languages, multilingual, greek, german, english, stemming, stopwords]
category: Text Analysis
order: 5
related_pages: [analysis, token-filters, tokenizers]
---

# Languages

Sigmie has index builders for English, German, and Greek. Each builder adds token filters for its language: stemmers, a stopword list, and a lowercase filter. The German builder also has a normalizer.

`language()` takes a language instance and returns that language's builder. The builder has every method of the index builder, plus the language filter methods:

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->language(new English) // [tl! highlight]
    ->properties($props)
    ->englishLowercase()
    ->englishStopwords()
    ->englishStemmer()
    ->create();
```

> **Warning:** Call `language()` directly after `newIndex()`. The language builder copies only the alias, the shard and replica counts, the registered APIs, and the collection hooks. It drops properties, token filters, char filters, and the tokenizer that you set before `language()`.

The language filters are token filters on the index `default` analyzer. They run in the order you add them, together with any generic [token filters](token-filters.md). Add the lowercase filter before the stopwords and the stemmer, because both compare lowercase words.

Each method takes an optional `$name`. Without one, the filter uses the default name in the tables below. A name must be unique in the index.

## English

```php
use Sigmie\Languages\English\English;

$sigmie->newIndex('articles')
    ->language(new English)
    ->englishLowercase()
    ->englishPossessiveStemming()
    ->englishStopwords()
    ->englishStemmer()
    ->create();
```

| Method | Default name | Elasticsearch filter | Effect |
|--------|--------------|----------------------|--------|
| `englishLowercase()` | `english_lowercase` | `lowercase` | Lowercases each token |
| `englishStopwords()` | `english_stopwords` | `stop`, `stopwords: _english_` | Removes English stopwords |
| `englishPossessiveStemming()` | `english_stemmer_possessive` | `stemmer`, `language: possessive_english` | Removes a trailing `'s` |
| `englishStemmer()` | `english_stemmer` | `stemmer`, `language: english` | Porter stemmer |
| `englishPorter2Stemmer()` | `english_stemmer_porter_2` | `stemmer`, `language: porter2` | Snowball Porter2 stemmer |
| `englishLightStemmer()` | `english_stemmer_light` | `stemmer`, `language: light_english` | KStem stemmer |
| `englishMinimalStemmer()` | `english_stemmer_minimal` | `stemmer`, `language: minimal_english` | Removes plural endings |
| `englishLovinsStemmer()` | `english_stemmer_lovins` | `stemmer`, `language: lovins` | Lovins stemmer |

Use one stemmer per analyzer. A second stemmer stems tokens that the first one already changed. `englishPossessiveStemming()` is not a full stemmer, so you can add it before one.

With `englishStemmer()`, the text `running relational` produces the tokens `run` and `relat`.

> **Warning:** On Elasticsearch, an analyzer with `englishLovinsStemmer()` returns no tokens. Use another English stemmer there.

## German

```php
use Sigmie\Languages\German\German;

$sigmie->newIndex('artikel')
    ->language(new German)
    ->germanLowercase()
    ->germanStopwords()
    ->germanNormalize()
    ->germanStemmer()
    ->create();
```

| Method | Default name | Elasticsearch filter | Effect |
|--------|--------------|----------------------|--------|
| `germanLowercase()` | `german_lowercase` | `lowercase` | Lowercases each token |
| `germanStopwords()` | `german_stopwords` | `stop`, `stopwords: _german_` | Removes German stopwords |
| `germanNormalize()` | `german_normalization` | `german_normalization` | Folds umlauts and `ß` |
| `germanStemmer()` | `german_stemmer` | `stemmer`, `language: german` | German stemmer |
| `germanStemmer2()` | `german_stemmer_2` | `stemmer`, `language: german2` | German2 stemmer |
| `germanLightStemmer()` | `german_stemmer_light` | `stemmer`, `language: light_german` | Light German stemmer |
| `germanMinimalStemmer()` | `german_stemmer_minimal` | `stemmer`, `language: minimal_german` | Minimal German stemmer |

`germanNormalize()` replaces `ä`, `ö`, and `ü` with `a`, `o`, and `u`, and `ß` with `ss`. With it, `Tür` and `Tur` produce the same token, so a query matches whether or not the user types the umlaut. Add it after `germanStopwords()`, because the stopword list contains umlauts.

## Greek

```php
use Sigmie\Languages\Greek\Greek;

$sigmie->newIndex('arthra')
    ->language(new Greek)
    ->greekLowercase()
    ->greekStopwords()
    ->greekStemmer()
    ->create();
```

| Method | Default name | Elasticsearch filter | Effect |
|--------|--------------|----------------------|--------|
| `greekLowercase()` | `greek_lowercase` | `lowercase`, `language: greek` | Lowercases, removes accents, and changes `ς` to `σ` |
| `greekStopwords()` | `greek_stopwords` | `stop`, `stopwords: _greek_` | Removes Greek stopwords |
| `greekStemmer()` | `greek_stemmer` | `stemmer`, `language: greek` | Greek stemmer |

Use `greekLowercase()` instead of the generic `lowercase()` for Greek text. The Greek stemmer expects tokens without accents and with `σ` for every sigma, which only `greekLowercase()` produces. Add it before `greekStemmer()`.

## Language filters on one field

The language builders configure the index `default` analyzer. To give one field its own language, pass the filter classes to `tokenFilter()` in [`withNewAnalyzer()`](mappings.md):

```php
use Sigmie\Index\NewAnalyzer;
use Sigmie\Languages\English\Filter\Stemmer as EnglishStemmer;
use Sigmie\Languages\German\Filter\Normalize as GermanNormalize;
use Sigmie\Languages\German\Filter\Stemmer as GermanStemmer;

$props->text('description_en')
    ->withNewAnalyzer(function (NewAnalyzer $analyzer) {
        $analyzer->lowercase();
        $analyzer->tokenFilter(new EnglishStemmer('description_en_stemmer')); // [tl! highlight]
    });

$props->text('description_de')
    ->withNewAnalyzer(function (NewAnalyzer $analyzer) {
        $analyzer->lowercase();
        $analyzer->tokenFilter(new GermanNormalize('description_de_normalize'));
        $analyzer->tokenFilter(new GermanStemmer('description_de_stemmer'));
    });
```

Each filter class lives in `Sigmie\Languages\<Language>\Filter` and takes its name as the only constructor argument. Pass a name to each instance. A class without one uses its default name, and a second filter with that name in the same index throws an `Exception`.

## See also

- [Text Analysis](analysis.md): how analyzers turn text into tokens.
- [Token Filters](token-filters.md): generic filters that work in any language.
- [Indices](index.md): index configuration.
