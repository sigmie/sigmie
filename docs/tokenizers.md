---
title: Tokenizers
short_description: Split text into tokens
keywords: [tokenizers, tokenization, tokens, text splitting, analysis]
category: Text Analysis
order: 2
related_pages: [analysis, token-filters, char-filters]
---

# Tokenizers

The tokenizer is the middle stage of the [analysis pipeline](analysis.md). It takes a string and produces tokens — typically words, but the rules depend on which tokenizer you pick.

For text like:

```
"Make your user's search experience great"
```

A whitespace tokenizer produces:

```
"Make" "your" "user's" "search" "experience" "great"
```

Sigmie has tokenizers for word boundaries, whitespace, patterns, paths, non-letters, and a no-op that keeps the input as one token.

## Word boundaries

Produces a token at every word boundary (handles punctuation):

```php
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;

$analyzer->tokenizer(new WordBoundaries(name: 'word_boundaries', maxTokenLength: 255));

// Or via the builder shortcut:
$analyzer->tokenizeOnWordBoundaries(maxTokenLength: 255);
```

`maxTokenLength` defaults to 255.

Example:

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

Punctuation is absorbed into the boundary.

## Whitespace

Splits on whitespace characters only — punctuation stays attached to neighboring tokens:

```php
use Sigmie\Index\Analysis\Tokenizers\Whitespace;

$analyzer->tokenizer(new Whitespace(name: 'whitespace_tokenizer'));

// Or:
$analyzer->tokenizeOnWhitespaces();
```

Same input as above:

```
"Aw" "shucks," "pluto." "I" "can't" "be" "mad" "at" "ya!"
```

`shucks,`, `pluto.`, and `ya!` keep their punctuation.

## No-op

Treats the entire input as a single token. Useful when you want exact-match behavior on text fields:

```php
use Sigmie\Index\Analysis\Tokenizers\Noop;

$analyzer->tokenizer(new Noop(name: 'noop_tokenizer'));

// Or:
$analyzer->dontTokenize();
```

```
"If you ain't scared, you ain't alive."

→ "If you ain't scared, you ain't alive."
```

## Pattern

Splits at every match of a regular expression. The matched text **is not** included in any token:

```php
use Sigmie\Index\Analysis\Tokenizers\Pattern;

$analyzer->tokenizer(new Pattern(name: 'pattern_tokenizer', ','));

// Or:
$analyzer->tokenizeOnPattern(',');
```

```
"Though at times it may feel like the sky is falling around you, never give up, for every day is a new day"

→ "Though at times it may feel like the sky is falling around you"
→ " never give up"
→ " for every day is a new day"
```

## Simple pattern

Outputs each match of the pattern as a token (the inverse of `Pattern`):

```php
use Sigmie\Index\Analysis\Tokenizers\SimplePattern;

$analyzer->tokenizer(new SimplePattern(name: 'simple_pattern', "'.*'"));

// Or:
$analyzer->tokenizeOnPatternMatch("'.*'");
```

```
"I remember daddy told me 'Fairytales can come true'."

→ "'Fairytales can come true'"
```

Only the quoted phrase becomes a token.

## Path hierarchy

Produces a token at every level of a hierarchical path:

```php
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;

$analyzer->tokenizer(new PathHierarchy(delimiter: '/'));

// Or:
$analyzer->tokenizePathHierarchy(delimiter: '/');
```

Default delimiter is `/`.

```
"Disney/Movies/Musical/Sleeping Beauty"

→ "Disney"
→ "Disney/Movies"
→ "Disney/Movies/Musical"
→ "Disney/Movies/Musical/Sleeping Beauty"
```

Useful for filtering on path prefixes — searching "Disney/Movies" matches every nested entry.

## Non-letter

Splits on any character that isn't a letter:

```php
use Sigmie\Index\Analysis\Tokenizers\NonLetter;

$analyzer->tokenizer(new NonLetter);

// Or:
$analyzer->tokenizeOnNonLetter();
```

```
"To infinity … and beyond!"

→ "To"
→ "infinity"
→ "and"
→ "beyond"
```

## See also

- [Analysis](analysis.md) — the full pipeline.
- [Token Filters](token-filters.md) — transforming tokens after tokenization.
- [Character Filters](char-filters.md) — pre-processing before tokenization.
