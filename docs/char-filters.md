---
title: Character Filters
short_description: Pre-process text before tokenization
keywords: [char filters, character filters, html strip, char mapping, preprocessing]
category: Text Analysis
order: 4
related_pages: [tokenizers, token-filters, analysis]
---

# Character Filters

Character filters run **before** the [tokenizer](tokenizers.md). They operate on the raw string — stripping HTML, mapping characters, applying regex substitutions — so the tokenizer sees clean input.

Sigmie ships three: HTML strip, character mapping, and pattern replace.

## HTML strip

Remove HTML tags from input text:

```php
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;

$analyzer->charFilter(new HTMLStrip);

// Or:
$analyzer->stripHTML();
```

```
"<span>Some people are worth melting for.</span>"
   │
   ▼ Strip HTML
"Some people are worth melting for."
```

Use this for crawled web content, rich-text fields, or anywhere user input might contain HTML.

## Character mapping

Replace specific substrings with replacements:

```php
use Sigmie\Index\Analysis\CharFilter\Mapping;

$analyzer->charFilter(new Mapping(
    name: 'mapping_char_filter',
    mappings: [
        ':)' => 'happy',
        ':(' => 'sad',
    ],
));

// Or:
$analyzer->mapChars([
    ':)' => 'happy',
    ':(' => 'sad',
]);
```

```
"Even miracles take a little time. :)"
   │
   ▼ Map Chars (":)" → "happy")
"Even miracles take a little time. happy"
```

Mappings are literal substring substitutions — not regex.

## Pattern replace

Apply a regex substitution:

```php
use Sigmie\Index\Analysis\CharFilter\Pattern;

$analyzer->charFilter(new Pattern(
    name: 'pattern_replace_char_filter',
    pattern: ':D|:\)',
    replace: 'happy',
));

// Or:
$analyzer->patternReplace(pattern: ':D|:\)', replace: 'happy');
```

```
"This is the perfect time to panic! :D :)"
   │
   ▼ Pattern Replace (":D|:\\)" → "happy")
"This is the perfect time to panic! happy happy"
```

Use pattern replace for edge cases mapping can't handle — variable-length matches, alternations, anchors.

## See also

- [Tokenizers](tokenizers.md) — what runs after the char filters.
- [Token Filters](token-filters.md) — transforming individual tokens.
- [Analysis](analysis.md) — the full pipeline.
