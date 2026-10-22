---
title: Token Filters
short_description: Sigmie token filter reference for changing Elasticsearch tokens after the tokenizer, covering case, stopwords, stemming, synonyms, shingles, and limits.
keywords: [token filters, stemming, synonyms, lowercase, stopwords]
category: Text Analysis
order: 3
related_pages: [tokenizers, analysis, char-filters, language]
---

# Token Filters

Token filters change, add, or remove tokens after the [tokenizer](tokenizers.md) runs. An analyzer runs its token filters in the order you add them. For why the order matters, see [Filter order changes the result](analysis.md#filter-order-changes-the-result).

The index builder and `NewAnalyzer` have the same token filter methods. The examples call them on `$analyzer`, the `NewAnalyzer` that `withNewAnalyzer()` passes to its callback. For English, German, and Greek filters, see [Languages](language.md).

| Builder method | Elasticsearch type | Effect |
|----------------|--------------------|--------|
| `lowercase()` | `lowercase` | Lowercases each token |
| `uppercase()` | `uppercase` | Uppercases each token |
| `trim()` | `trim` | Removes leading and trailing whitespace |
| `asciiFolding()` | `asciifolding` | Replaces accented characters with ASCII |
| `decimalDigit()` | `decimal_digit` | Replaces Unicode digits with `0` to `9` |
| `stopwords(array $stopwords)` | `stop` | Removes listed tokens |
| `stemming(array $stemming)` | `stemmer_override` | Replaces listed words with a stem |
| `keywords(array $keywords)` | `keyword_marker` | Protects listed tokens from stemmers |
| `synonyms(array $synonyms, bool $expand = true)` | `synonym` | Adds or replaces synonyms |
| `oneWaySynonyms(array $synonyms)` | `synonym` | Replaces variants with one term |
| `twoWaySynonyms(array $synonyms)` | `synonym` | Makes terms equivalent |
| `unique(bool $onlyOnSamePosition = false)` | `unique` | Removes duplicate tokens |
| `tokenLimit(int $maxTokenCount)` | `limit` | Keeps the first N tokens |
| `truncate(int $length = 10)` | `truncate` | Cuts each token to a length |
| `shingle(int $minSize = 2, int $maxSize = 2)` | `shingle` | Adds word n-grams |
| `tokenFilter(TokenFilter $tokenFilter)` | Set by the instance | Adds any token filter instance |

## Token filter names

Each builder method takes an optional `$name` as its last argument. Without one, Sigmie generates a name from the method's prefix and a random suffix, such as `stopwords` followed by five random characters. A name must be unique in the index. Adding a token filter with a name that the index already has throws an `Exception` with the message ``Token filter `<name>` already exists.``

## Lowercase and uppercase

Change the case of each token.

```php
$analyzer->lowercase();
$analyzer->uppercase();
```

```
"You" "better" "be" "back" "ASAP"
   │
   ▼ Lowercase
"you" "better" "be" "back" "asap"
```

Without `lowercase()`, the token `Matrix` does not match a query for `matrix`.

## Trim

Removes leading and trailing whitespace from each token.

```php
$analyzer->trim();
```

```
" never give up"   →   "never give up"
" for every day"   →   "for every day"
```

The [pattern tokenizer](tokenizers.md#pattern) can leave whitespace at the start of a token. Whitespace and word boundary tokens have none to remove.

## ASCII folding

Replaces letters with diacritics by their ASCII form.

```php
$analyzer->asciiFolding();
```

```
"manténgase"   →   "mantengase"
```

With `asciiFolding()` on the field, a query matches whether or not the user types the accent.

## Decimal digit

Replaces digits from other Unicode scripts with the ASCII digits `0` to `9`.

```php
$analyzer->decimalDigit();
```

```
"໑" "໒" "໓" "໔" "໕"     (Lao digits)
   │
   ▼ Decimal digit
"1" "2" "3" "4" "5"
```

## Stopwords

Removes each token that is in the list. The comparison is case-sensitive, so add `lowercase()` first when the list is in lowercase.

```php
$analyzer->stopwords(['but', 'not']);
```

```
"Ladies" "do" "not" "start" "fights" "but" "they" "can" "finish" "them"
   │
   ▼ Stopwords ("not", "but")
"Ladies" "do" "start" "fights" "they" "can" "finish" "them"
```

## Stemming

Replaces listed words with a stem that you choose. Each rule is a pair: the stem first, then the words that map to it.

```php
$analyzer->stemming([
    ['go', ['going']],
    ['mouse', ['mice']],
]);
```

```
"Where" "are" "you" "going"
   │
   ▼ Stemming
"Where" "are" "you" "go"
```

`stemming()` applies only the rules you list. For algorithmic stemmers, see [Languages](language.md).

## Keywords

Marks tokens as keywords. Stemmers later in the chain leave keyword tokens unchanged. Add `keywords()` before the stemmer it guards.

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
   ▼ Keywords mark "going"
   ▼ Stemming skips "going"
"Where" "are" "you" "going"
```

## Synonyms

Sigmie has three synonym methods. All three create an Elasticsearch `synonym` filter.

### One-way synonyms

Replaces each variant with one term. Each rule is a pair: the term first, then its variants.

```php
$analyzer->oneWaySynonyms([
    ['ipod', ['i-pod', 'i pod']],
]);
```

Sigmie writes the rule `i-pod, i pod => ipod`. The filter turns `i-pod` and `i pod` into `ipod` and leaves `ipod` unchanged. The same analyzer runs on queries, so a search for any of the three spellings matches a document with any of the others.

### Two-way synonyms

Makes every term in a group equivalent. Each group is a list of terms.

```php
$analyzer->twoWaySynonyms([
    ['joy', 'fun'],
    ['friend', 'buddy', 'partner'],
]);
```

Sigmie writes one rule for each group, such as `joy, fun`, with `expand` set to `true`. Each term produces every term in its group at the same position:

```
"It's" "kind" "of" "fun" "to" "do" "the" "impossible"
   │
   ▼ Synonyms (joy, fun)
"It's" "kind" "of" "fun" "joy" "to" "do" "the" "impossible"
```

### The `synonyms()` method

Takes both rule formats in one list and sets `expand` yourself. `expand` defaults to `true`.

```php
$analyzer->synonyms([
    ['joy', 'fun'],
    ['ipod', ['i-pod', 'i pod']],
], expand: true);
```

With `expand: false`, a group such as `joy, fun` maps every term to the first term, `joy`. One-way rules ignore `expand`.

## Unique

Removes duplicate tokens.

```php
$analyzer->unique();
```

```
"I" "was" "hiding" "under" "your" "porch" "because" "I" "love" "you"
   │
   ▼ Unique
"I" "was" "hiding" "under" "your" "porch" "because" "love" "you"
```

With `onlyOnSamePosition: true`, the filter removes a duplicate only when it is at the same position, for example a synonym equal to the original token.

## Token limit

Keeps the first `maxTokenCount` tokens and drops the rest. `maxTokenCount` has no default.

```php
$analyzer->tokenLimit(maxTokenCount: 5);
```

```
"I" "was" "hiding" "under" "your" "porch" "because" "I" "love" "you"
   │
   ▼ Token limit 5
"I" "was" "hiding" "under" "your"
```

## Truncate

Cuts each token to at most `length` characters. `length` defaults to `10`.

```php
$analyzer->truncate(length: 10);
```

```
"Supercalifragilisticexpialidocious"
   │
   ▼ Truncate 10
"Supercalif"
```

## Shingle

Adds tokens made of neighboring words, from `minSize` to `maxSize` words long. Both default to `2`. The original single-word tokens stay.

```php
$analyzer->shingle(minSize: 2, maxSize: 2);
```

```
"quick" "brown" "fox"
   │
   ▼ Shingle 2
"quick" "quick brown" "brown" "brown fox" "fox"
```

## Custom token filters

`tokenFilter()` adds any object that implements `Sigmie\Index\Contracts\TokenFilter`. To use a filter from an Elasticsearch plugin, extend the `TokenFilter` class and return the plugin's type:

```php
use Sigmie\Index\Analysis\TokenFilter\TokenFilter;

use function Sigmie\Functions\name_configs;

class Greeklish extends TokenFilter
{
    public function type(): string
    {
        return 'skroutz_greeklish';
    }

    public static function fromRaw(array $raw): static
    {
        [$name] = name_configs($raw);

        return new static($name);
    }
}

$analyzer->tokenFilter(new Greeklish('greeklish'));
```

When Sigmie reads an existing index, for example with `$sigmie->index()`, it rebuilds each filter from the Elasticsearch `type`. A type that Sigmie does not know becomes a generic filter that keeps its settings. To get your own class back, register it by type:

```php
TokenFilter::filterMap([
    'skroutz_greeklish' => Greeklish::class, // [tl! highlight]
]);
```

The keys are Elasticsearch filter types, not filter names. `CharFilter::filterMap()` and `Tokenizer::filterMap()` do the same for char filters and tokenizers. Those two have no generic fallback and throw an `Exception` for an unknown type.

## See also

- [Tokenizers](tokenizers.md): the stage before token filters.
- [Character Filters](char-filters.md): changes to the string before the tokenizer.
- [Text Analysis](analysis.md): the full pipeline.
- [Languages](language.md): stemmers, stopwords, and normalizers for English, German, and Greek.
