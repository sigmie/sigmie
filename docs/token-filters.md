---
title: Token Filters
short_description: Transform tokens with stemming, synonyms, lowercase, and more
keywords: [token filters, stemming, synonyms, lowercase, stopwords]
category: Text Analysis
order: 3
related_pages: [tokenizers, analysis, char-filters, language]
---

# Token Filters

Token filters run after the [tokenizer](tokenizers.md). Each filter transforms or removes tokens — lowercasing, stemming, dropping stopwords, applying synonyms.

Filters run in the order you declare them. The order matters: lowercasing before applying stopwords (which are usually defined in lowercase) is correct; doing it the other way around drops nothing.

## Stemming

Reduces words to a root form so "going" matches "go":

```php
$analyzer->stemming([
    ['go', ['going']],
]);
```

```
"Where" "are" "you" "going"
   │
   ▼ Stemming
"Where" "are" "you" "go"
```

## Stopwords

Drop common words:

```php
$analyzer->stopwords(['but', 'not']);
```

```
"Ladies" "do" "not" "start" "fights" "but" "they" "can" "finish" "them"
   │
   ▼ Stopwords ("not", "but")
"Ladies" "do" "start" "fights" "they" "can" "finish" "them"
```

## Trim

Remove leading and trailing whitespace from each token:

```php
$analyzer->trim();
```

Useful after pattern-based tokenization that can leave whitespace attached:

```
" never give up"   →   "never give up"
" for every day"   →   "for every day"
```

## Unique

Remove duplicate tokens:

```php
$analyzer->unique(onlyOnSamePosition: false);
```

```
"I" "was" "hiding" "under" "your" "porch" "because" "I" "love" "you"
   │
   ▼ Unique
"I" "was" "hiding" "under" "your" "porch" "because" "love" "you"
```

## Synonyms

### One-way

Replace specified terms with a canonical form:

```php
$analyzer->oneWaySynonyms([
    'ipod' => ['i-pod', 'i pod'],
]);
```

Anywhere `i-pod` or `i pod` appears, it's also indexed as `ipod` — but searches for `i-pod` don't match documents containing `ipod`.

### Two-way

Map a set of terms to each other:

```php
$analyzer->synonyms([
    ['joy', 'fun'],
]);
```

`fun` and `joy` are interchangeable — either matches documents containing the other.

```
"It's" "kind" "of" "fun" "to" "do" "the" "impossible"
   │
   ▼ Synonyms (fun ↔ joy)
"It's" "kind" "of" "fun" "joy" "to" "do" "the" "impossible"
```

## Lowercase / Uppercase

```php
$analyzer->lowercase();
$analyzer->uppercase();
```

Lowercase is part of nearly every analyzer — without it, "Matrix" doesn't match a query for "matrix".

```
"You" "better" "be" "back" "ASAP"
   │
   ▼ Lowercase
"you" "better" "be" "back" "asap"
```

## Decimal digit

Convert non-ASCII digits to ASCII:

```php
$analyzer->decimalDigit();
```

```
"໑" "໒" "໓" "໔" "໕"     (Lao digits)
   │
   ▼ Decimal Digit
"1" "2" "3" "4" "5"
```

## ASCII folding

Strip diacritics:

```php
$analyzer->asciiFolding();
```

```
"manténgase"   →   "mantengase"
```

Useful when users might or might not type accents.

## Token limit

Keep only the first N tokens:

```php
$analyzer->tokenLimit(maxTokenCount: 5);
```

```
"I" "was" "hiding" "under" "your" "porch" "because" "I" "love" "you"
   │
   ▼ Token Limit 5
"I" "was" "hiding" "under" "your"
```

## Truncate

Limit each token's length:

```php
$analyzer->truncate(length: 10);
```

```
"Supercalifragilisticexpialidocious"
   │
   ▼ Truncate 10
"Supercalif"
```

## Keywords

Protect specific terms from later filters — for example, prevent stemming on a brand name:

```php
$analyzer
    ->keywords(['going'])
    ->stemming([
        ['go', ['going']],
    ]);
```

```
"Where" "are" "you" "going"
   │
   ▼ Keywords protect "going"
   ▼ Stemming would normally turn "going" into "go" — but doesn't here
"Where" "are" "you" "going"
```

## Custom token filters

Register your own filter classes by name:

```php
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

TokenFilter::filterMap([
    'skroutz_greeklish' => SkroutzGreeklish::class,
    'skroutz_stem_greek' => SkroutzGreekStemmer::class,
]);
```

`SkroutzGreeklish` and `SkroutzGreekStemmer` are your classes implementing the token filter contract.

## See also

- [Tokenizers](tokenizers.md) — splitting text into tokens before the filters run.
- [Character Filters](char-filters.md) — preprocessing before tokenization.
- [Analysis](analysis.md) — the full pipeline.
- [Languages](language.md) — pre-built filter chains for English, German, Greek.
