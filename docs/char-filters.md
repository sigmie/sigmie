---
title: Character Filters
short_description: Sigmie character filter reference for cleaning raw text before Elasticsearch tokenizes it, with HTML strip, literal character mapping, and regex replace.
keywords: [char filters, character filters, html strip, char mapping, preprocessing]
category: Text Analysis
order: 4
related_pages: [tokenizers, token-filters, analysis]
---

# Character Filters

Character filters change the raw string before the [tokenizer](tokenizers.md) splits it. An analyzer runs its char filters in the order you add them. For where char filters sit in the pipeline, see [Text Analysis](analysis.md).

The index builder and `NewAnalyzer` have the same char filter methods. The examples call them on `$analyzer`, the `NewAnalyzer` that `withNewAnalyzer()` passes to its callback.

| Builder method | Class | Elasticsearch type |
|----------------|-------|--------------------|
| `stripHTML(?string $name = null)` | `HTMLStrip` | `html_strip` |
| `mapChars(array $mappings, ?string $name = null)` | `Mapping` | `mapping` |
| `patternReplace(string $pattern, string $replace, ?string $name = null)` | `Pattern` | `pattern_replace` |
| `charFilter(CharFilter $charFilter)` | Any char filter instance | Set by the instance |

## Char filter names

Each builder method takes an optional `$name`. Without one, Sigmie generates a name from a prefix and a random suffix, such as `html_strip` followed by five random characters. A name must be unique in the index. Adding a char filter with a name that the index already has throws an `Exception` with the message ``Char filter name `<name>` already exists.``

## HTML strip

Removes HTML tags and decodes HTML entities.

```php
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;

$analyzer->stripHTML();

// Or, with the class:
$analyzer->charFilter(new HTMLStrip);
```

```
"<span>Some people are worth melting for.</span>"
   │
   ▼ Strip HTML
"Some people are worth melting for."
```

The `HTMLStrip` class uses the name `html_strip` when you pass no name.

## Character mapping

Replaces each key with its value. Keys are literal strings, not regular expressions.

```php
use Sigmie\Index\Analysis\CharFilter\Mapping;

$analyzer->mapChars([
    ':)' => 'happy',
    ':(' => 'sad',
]);

// Or, with the class:
$analyzer->charFilter(new Mapping(
    name: 'emoticons',
    mappings: [
        ':)' => 'happy',
        ':(' => 'sad',
    ],
));
```

```
"Even miracles take a little time. :)"
   │
   ▼ Map chars (":)" → "happy")
"Even miracles take a little time. happy"
```

## Pattern replace

Replaces every match of a Java regular expression. The replacement can refer to capture groups as `$1`, `$2`, and so on.

```php
use Sigmie\Index\Analysis\CharFilter\Pattern;

$analyzer->patternReplace(pattern: ':D|:\)', replace: 'happy');

// Or, with the class:
$analyzer->charFilter(new Pattern(
    name: 'smileys',
    pattern: ':D|:\)',
    replacement: 'happy',
));
```

```
"This is the perfect time to panic! :D :)"
   │
   ▼ Pattern replace (":D|:\)" → "happy")
"This is the perfect time to panic! happy happy"
```

> **Note:** The builder argument is `replace`. The `Pattern` constructor argument is `replacement`.

`mapChars()` matches fixed strings. `patternReplace()` matches a pattern, so it handles alternatives, variable-length matches, and anchors.

## See also

- [Tokenizers](tokenizers.md): the stage after char filters.
- [Token Filters](token-filters.md): changes to individual tokens.
- [Text Analysis](analysis.md): the full pipeline.
