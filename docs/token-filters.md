---
title: Token Filters
short_description: Transform and filter tokens with stemming, synonyms, and more
keywords: [token filters, stemming, synonyms, lowercase, stopwords]
category: Text Analysis
order: 3
related_pages: [tokenizers, analysis, char-filters, language]
---

## Token Filters

Filters are applied in the order that you specify them. Here are some examples of how each filter works:

###  Stemming
The Stemming filter reduces words to their root form.

For example, "going" becomes "go":
```php
$newAnalyzer->stemming([
    ['go', ['going']]
    // more
]);
```

```php
 "Where"                  
 "are"                     
 "you"                    
 "going"                  
 ------------------------ 
 Stemming "going" -> "go" 
 ------------------------ 
 "Where"                  
 "are"                     
 "you"                    
 "going"                   // [tl! remove]
 "go"                      // [tl! add]
```

### Stopwords
The Stopwords filter removes common words that do not contribute to the meaning of a phrase.

For example, "but" and "not" are removed from the phrase:

```php
$newAnalyzer->stopwords(['but']);
```

```php
 "Ladies"              
 "do"                   
 "not"                 
 "start"               
 "fights"              
 "but"                 
 "they"                
 "can"                 
 "finish"              
 "them"                
 --------------------- 
 Stopwords "but","not" 
 --------------------- 
 "Ladies"              
 "do"                  
 "not"                  // [tl! remove]
 "start"               
 "fights"              
 "but"                  // [tl! remove]
 "they"                
 "can"                 
 "finish"              
 "them"                
```

### Unique
The Unique filter removes duplicate words.

For example, the second "I" is removed from the phrase:
```php
$newAnalyzer->unique(onlyOnSamePosition: false);
```
```php
 "I"
 "was"
 "hiding"
 "under"
 "your"
 "porch"
 "because"
 "I"
 "love"
 "you"
 --------------------- 
 Unique
 --------------------- 
 "I" // [tl! highlight]
 "was"
 "hiding"
 "under"
 "your"
 "porch"
 "because"
 "I"  // [tl! remove]
 "love"
 "you"
```


### Trim
The Trim filter removes leading and trailing whitespace from words.

For example, " never give up" becomes "never give up":
```php
$newAnalyzer->trim();
```

```php
  "Though at times it may feel like the sky is falling around you"
  " never give up"
  " for every day is a new day"
 --------------------- 
 Trim
 --------------------- 
  "Though at times it may feel like the sky is falling around you" 
  " never give up" // [tl! remove]
  "never give up" // [tl! add]
  " for every day is a new day" // [tl! remove]
  "for every day is a new day" // [tl! add]
```


### One-Way Synonym
The One-Way Synonym filter replaces a word with its synonym.

For example, "fun" becomes "joy":
```php
$newAnalyzer->oneWaySynonyms([
                'ipod' => ['i-pod', 'i pod'],
            ]);
```

```php
$newAnalyzer->synonyms([
                ['joy' ,['fun']]
            ]);
```

```php
 "It’s"
 "kind"
 "of"
 "fun"
 "to"
 "do"
 "the"
 "impossible"
 --------------------- 
 Synonyms "fun" -> "joy"
 --------------------- 
 "It’s"
 "kind"
 "of"
 "fun" // [tl! remove]
 "joy" // [tl! add]
 "to"
 "do"
 "the"
 "impossible"
```

### Two-Way Synonyms
The Two-Way Synonyms filter replaces a word with its synonym and vice versa.

For example, "fun" becomes "joy" and "joy" becomes "fun":
```php
$newAnalyzer->synonyms([
                ['joy' ,'fun']
            ]);
```

```php
 "It’s"
 "kind"
 "of"
 "fun"
 "to"
 "do"
 "the"
 "impossible"
 --------------------- 
 Synonyms "fun", "joy"
 --------------------- 
 "It’s"
 "kind"
 "of"
 "fun" // [tl! highlight]
 "joy" // [tl! add]
 "to"
 "do"
 "the"
 "impossible"
```


### Lowercase
The Lowercase filter converts all characters in a word to lowercase.

For example, "ASAP" becomes "asap":
```php
$newAnalyzer->lowercase();
```

```php
 "You"
 "better"
 "be"
 "back"
 "ASAP"
 --------------------- 
 Lowercase
 --------------------- 
 "You" // [tl! remove]
 "you" // [tl! add]
 "better"
 "be"
 "back"
 "ASAP" // [tl! remove]
 "asap" // [tl! add]
```

### Uppercase

The Uppercase filter converts all characters in a word to uppercase.

For example, "Miserable" becomes "MISERABLE":

```php
$newAnalyzer->uppercase();
```
```php
"Miserable"
"darling"
"as"
"usual"
"perfectly"
"wretched"
 --------------------- 
 Uppercase
 --------------------- 
"Miserable" // [tl! remove]
"darling" // [tl! remove]
"as" // [tl! remove]
"usual" // [tl! remove]
"perfectly" // [tl! remove]
"wretched" // [tl! remove]
"MISERABLE" // [tl! add]
"DARLING" // [tl! add]
"AS" // [tl! add]
"USUAL" // [tl! add]
"PERFECTLY" // [tl! add]
"WRETCHED" // [tl! add]
```

### Decimal Digit
The Decimal Digit filter converts non-ASCII digits to their ASCII equivalents.

For example, Lao digits are converted to Arabic numerals:
```php
$newAnalyzer->decimalDigit();
```
```php
// Lao Digits from 1 to 5
 "໑"
 "໒"
 "໓"
 "໔"
 "໕"
 --------------------- 
 Decimal Digit
 --------------------- 
 "໑" // [tl! remove]
 "໒" // [tl! remove]
 "໓" // [tl! remove]
 "໔" // [tl! remove]
 "໕" // [tl! remove]
 "1" // [tl! add]
 "2" // [tl! add]
 "3" // [tl! add]
 "4" // [tl! add]
 "5" // [tl! add]
```

### Ascii Folding
The Ascii Folding filter removes diacritics from characters.

For example, "manténgase" becomes "mantengase":
```php
$newAnalyzer->asciiFolding();
```
```php
 "Por"
 "favor"
 "manténgase"
 "alejado"
 "de"
 "las"
 "puertas"
 --------------------- 
 Ascii Folding
 --------------------- 
  "Por"
  "favor"
  "manténgase" // [tl! remove]
  "mantengase"// [tl! add]
  "alejado"
  "de"
  "las"
  "puertas"
  ```

### Token Limit
The Token Limit filter limits the number of tokens in a phrase.

For example, only the first five words are kept in the phrase:
```php
$newAnalyzer->tokenLimit(maxTokenCount: 10);
```

```php
 "I"
 "was"
 "hiding"
 "under"
 "your"
 "porch"
 "because"
 "I"
 "love"
 "you"
 --------------------- 
 Token Limit 5
 --------------------- 
 "I"
 "was"
 "hiding"
 "under"
 "your"
 "porch" // [tl! remove]
 "because" // [tl! remove]
 "I"  // [tl! remove]
 "love" // [tl! remove]
 "you" // [tl! remove]
```

### Truncate
The Truncate filter limits the length of a word.

For example, "Supercalifragilisticexpialidocious" becomes "Supercalif":
```php
$newAnalyzer->truncate(length: 10);
```
```php
 "Supercalifragilisticexpialidocious"
 --------------------- 
 Truncate 10
 --------------------- 
 "Supercalifragilisticexpialidocious" // [tl! remove]
 "Supercalif" // [tl! add]
```

### Keywords
The Keywords filter prevents certain words from being modified by other filters.

For example, "going" is not stemmed to "go":
```php
$newAnalyzer
->keywords(['going'])
->stemming([
    ['go', ['going']]
]);
```

```php
 "Where"                  
 "are"                     
 "you"                    
 "going"                  
 ------------------------ 
 Keywords "going"
 ------------------------ 
 Stemming "going" -> "go" 
 ------------------------ 
 "Where"                  
 "are"                     
 "you"                    
 "going" // [tl! highlight]
```

## Registering Custom Token Filters 

You can register your own custom token filters. This can be done using the `TokenFilter::filterMap` method. This method accepts an associative array where the keys are the names of the custom filters and the values are the corresponding class names. Here is an example:

```php
        TokenFilter::filterMap([
            'skroutz_greeklish' => SkroutzGreeklish::class,
            'skroutz_stem_greek' => SkroutzGreekStemmer::class,
        ]);
```

In this example, two custom token filters are registered: `skroutz_greeklish` and `skroutz_stem_greek`. The `SkroutzGreeklish` and `SkroutzGreekStemmer` classes define the behavior of these filters.
