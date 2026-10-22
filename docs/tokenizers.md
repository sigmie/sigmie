---
title: Tokenizers
short_description: Sigmie tokenizer reference for splitting Elasticsearch text into tokens by word boundaries, whitespace, regex patterns, path levels, letters, or n-grams.
keywords: [tokenizers, tokenization, tokens, text splitting, analysis]
category: Text Analysis
order: 2
related_pages: [analysis, token-filters, char-filters]
---

# Tokenizers

The tokenizer splits a string into tokens. It is the middle stage of the [analysis pipeline](analysis.md): it runs after the [char filters](char-filters.md) and before the [token filters](token-filters.md).

An analyzer has exactly one tokenizer. Each tokenizer method replaces the previous one, so the last call wins. Without a tokenizer method, an analyzer uses word boundaries.

The index builder and `NewAnalyzer` have the same tokenizer methods. The examples call them on `$analyzer`, the `NewAnalyzer` that `withNewAnalyzer()` passes to its callback.

| Builder method | Class | Elasticsearch type | Token boundaries |
|----------------|-------|--------------------|------------------|
| `tokenizeOnWordBoundaries()` | `WordBoundaries` | `standard` | Unicode word boundaries |
| `tokenizeOnWhitespaces()` | `Whitespace` | `whitespace` | Whitespace |
| `dontTokenize()` | `Noop` | `keyword` | None. The whole input is one token. |
| `tokenizeOnPattern($pattern, $flags)` | `Pattern` | `pattern` | Each match of a Java regex |
| `tokenizeOnSimplePattern($pattern)` | `SimplePatternSplit` | `simple_pattern_split` | Each match of a Lucene regex |
| `tokenizeOnPatternMatch($pattern)` | `SimplePattern` | `simple_pattern` | Each match becomes a token |
| `tokenizePathHierarchy($delimiter)` | `PathHierarchy` | `path_hierarchy` | Each path level |
| `tokenizeOnNonLetter()` | `NonLetter` | `letter` | Any character that is not a letter |
| None | `Ngram` | `ngram` | Overlapping character sequences |
| `tokenizer(Tokenizer $tokenizer)` | Any tokenizer instance | Set by the instance | Set by the instance |

Every builder method also takes an optional `$name` as its last argument. Without one, Sigmie generates a name from a prefix and a random suffix. The class constructors take the name as their first argument.

## Word boundaries

Splits on Unicode word boundaries and drops most punctuation. This is the default tokenizer.

```php
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;

$analyzer->tokenizeOnWordBoundaries();

// Or, with the class:
$analyzer->tokenizer(new WordBoundaries(name: 'word_boundaries', maxTokenLength: 255));
```

```
"Aw shucks, pluto. I can't be mad at ya!"

→ "Aw"
→ "shucks"
→ "pluto"
→ "I"
→ "can't"
→ "be"
→ "mad"
→ "at"
→ "ya"
```

`maxTokenLength` defaults to `255`. Elasticsearch splits a longer token into pieces of that length. Only the class accepts `maxTokenLength`. `tokenizeOnWordBoundaries()` always uses `255`.

## Whitespace

Splits on whitespace only. Punctuation stays attached to the word next to it.

```php
use Sigmie\Index\Analysis\Tokenizers\Whitespace;

$analyzer->tokenizeOnWhitespaces();

// Or, with the class:
$analyzer->tokenizer(new Whitespace(name: 'whitespace_tokenizer'));
```

```
"Aw shucks, pluto. I can't be mad at ya!"

→ "Aw" "shucks," "pluto." "I" "can't" "be" "mad" "at" "ya!"
```

## No-op

Keeps the whole input as one token. Use it for exact matches on a text field.

```php
use Sigmie\Index\Analysis\Tokenizers\Noop;

$analyzer->dontTokenize();

// Or, with the class:
$analyzer->tokenizer(new Noop(name: 'noop_tokenizer'));
```

```
"If you ain't scared, you ain't alive."

→ "If you ain't scared, you ain't alive."
```

## Pattern

Splits at each match of a Java regular expression. The matched text is not part of any token.

```php
use Sigmie\Index\Analysis\Tokenizers\Pattern;

$analyzer->tokenizeOnPattern(',');

// Or, with the class:
$analyzer->tokenizer(new Pattern(name: 'comma_tokenizer', pattern: ','));
```

```
"Though at times it may feel like the sky is falling around you, never give up, for every day is a new day"

→ "Though at times it may feel like the sky is falling around you"
→ " never give up"
→ " for every day is a new day"
```

The tokens keep their leading spaces. Add the [`trim()` token filter](token-filters.md#trim) to remove them.

The optional `$flags` argument takes Java regex flags separated by `|`, for example `'CASE_INSENSITIVE|COMMENTS'`:

```php
$analyzer->tokenizeOnPattern('\s+and\s+', flags: 'CASE_INSENSITIVE');
```

## Simple pattern split

Splits at each match of a Lucene regular expression. Lucene regex has fewer features than Java regex, and Elasticsearch runs it faster.

```php
use Sigmie\Index\Analysis\Tokenizers\SimplePatternSplit;

$analyzer->tokenizeOnSimplePattern('_');

// Or, with the class:
$analyzer->tokenizer(new SimplePatternSplit(name: 'underscore_tokenizer', pattern: '_'));
```

```
"alpha_beta_gamma"

→ "alpha"
→ "beta"
→ "gamma"
```

## Simple pattern

Turns each match of a Lucene regular expression into a token and drops the rest of the text. It does the opposite of simple pattern split.

```php
use Sigmie\Index\Analysis\Tokenizers\SimplePattern;

$analyzer->tokenizeOnPatternMatch("'.*'");

// Or, with the class:
$analyzer->tokenizer(new SimplePattern(name: 'quoted_tokenizer', pattern: "'.*'"));
```

```
"I remember daddy told me 'Fairytales can come true'."

→ "'Fairytales can come true'"
```

> **Note:** The two method names look alike. `tokenizeOnSimplePattern()` splits at matches. `tokenizeOnPatternMatch()` keeps only the matches.

The `SimplePattern` class also takes a `$flags` argument. The `simple_pattern` tokenizer does not apply it: with `[A-Z]+` and `CASE_INSENSITIVE`, the input `abc ABC DEF 123` still produces only `ABC` and `DEF`.

## Path hierarchy

Produces one token for each level of a path.

```php
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;

$analyzer->tokenizePathHierarchy(delimiter: '/');

// Or, with the class:
$analyzer->tokenizer(new PathHierarchy(name: 'path_tokenizer', delimiter: '/'));
```

```
"Disney/Movies/Musical/Sleeping Beauty"

→ "Disney"
→ "Disney/Movies"
→ "Disney/Movies/Musical"
→ "Disney/Movies/Musical/Sleeping Beauty"
```

The delimiter defaults to `/`. Every path under `Disney/Movies` has the token `Disney/Movies`, so a term query for `Disney/Movies` matches all of them.

## Non-letter

Splits at every character that is not a letter. Digits and punctuation are dropped.

```php
use Sigmie\Index\Analysis\Tokenizers\NonLetter;

$analyzer->tokenizeOnNonLetter();

// Or, with the class:
$analyzer->tokenizer(new NonLetter(name: 'letter_tokenizer'));
```

```
"To infinity … and beyond!"

→ "To"
→ "infinity"
→ "and"
→ "beyond"
```

## N-gram

Produces every character sequence between `minGram` and `maxGram` characters long. There is no builder method. Pass an instance to `tokenizer()`.

```php
use Sigmie\Index\Analysis\Tokenizers\Ngram;

$analyzer->tokenizer(new Ngram(name: 'two_gram_tokenizer', minGram: 2, maxGram: 2));
```

```
"abcd"

→ "ab"
→ "bc"
→ "cd"
```

| Argument | Default | Elasticsearch setting |
|----------|---------|-----------------------|
| `minGram` | `3` | `min_gram` |
| `maxGram` | `3` | `max_gram` |
| `tokenChars` | `['letter']` | `token_chars` |

## See also

- [Text Analysis](analysis.md): the full pipeline.
- [Token Filters](token-filters.md): changes to tokens after the tokenizer.
- [Character Filters](char-filters.md): changes to the string before the tokenizer.
