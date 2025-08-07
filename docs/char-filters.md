## Introduction
Char filters are filters applied to texts **before** they reach the **Tokenizer** and they become **Tokens**. 

## HTML Strip
The **HTML Strip** removes all HTML from the analyzed text. You can use it by calling the `stripHTML` method on a `NewAnalyzer` instance or by passing a new instance of the `HTMLStrip` class to the `charFilter` method.

```php
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;

$newAnalyer->charFilter(new HTMLStrip);

// OR

$newAnalyer->stripHTML();
```

Here is an example of how a text containing the `<span>` HTML tags is transformed using the **Strip HTML** char filter.
```php
 "<span>Some people are worth melting for.</span>" 
 ------------------------------------------------- 
 Strip HTML                                        
 ------------------------------------------------- 
 "Some people are worth melting for."              
```

## Char Mapping
The **Char Mapping** filter replaces any occurrences of the passed string with another one. To use it either pass the replacements to the `mapChars` method on the `NewIndex` builder or use the `charFilter` method passing a `Mapping` class instance.
```php
use Sigmie\Index\Analysis\CharFilter\Mapping;

$newAnalyer->charFilter(new Mapping(
    name: 'mapping_char_filter',
    mappings: [
        ':)' => 'happy',
        ':(' => 'sad',
    ]
));

// OR

$newAnalyzer->mapChars([':)'=> 'happy']);
```

In the below example you can see how a text containing a happy emoji (`:)`) will look like after we replace it with the word `happy` using the **Map Chars** filter.
```php
 "Even miracles take a little time. :)"    
 ----------------------------------------- 
 Map Chars ":)" -> "happy"                 
 ----------------------------------------- 
 "Even miracles take a little time. happy" 
```

## Pattern replace
For handling any edge case you can use the **Pattern replace** filter, which will match a regex pattern and replace it with the given string.

You can use it by calling the `patternReplace` method on the `NewIndex` builder instance, or by passing an instance of the `Pattern` class to the `charFilter` method.
```php
use Sigmie\Index\Analysis\CharFilter\Pattern;

$newAnalyer->charFilter(new Pattern(
    name: 'pattern_replace_char_filter',
    pattern: ':D|:\)',
    replace: 'happy'
));

// OR

$newAnalyer->patternReplace(pattern: ':D|:\)', replace:'happy');
```

Here is an example of how your text will look after we apply the **Pattern Replace** filter to replace any match of the `:D|:)` pattern with the `happy` word.

```php
 "This is the perfect time to panic! :D :)"      
 ------------------------------------------------ 
 Pattern Replace ":D|:\)" -> "happy"              
 ------------------------------------------------ 
 "This is the perfect time to panic! happy happy" 
```
