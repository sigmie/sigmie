---
title: Tokenizers
short_description: Split text into tokens with various tokenization strategies
keywords: [tokenizers, tokenization, tokens, text splitting, analysis]
category: Text Analysis
order: 2
related_pages: [analysis, token-filters, char-filters]
---

Tokenization is the second step in the Elasticsearch analysis process. Once the analyzer has applied all char filters, it's time to split the text into tokens.

To do this Elasticsearch uses the so-called **Tokenizers**, which take a text and produce **tokens**. 

Tokens are nothing more than chunks of text.
If we take for example the text
```php
"Make your user’s search experience great"
```

and we tokenize it on every `whitespace` we will get the following tokens:

```php
"Make"
"your"
"user's"
"search"
"experience"
"great"
```

This simple process is called **Tokenization**.

## Introduction
All Elasticsearch Tokenizers are available in Sigmie. You can use them by calling the `tokenizer` method on a `NewAnalyzer` builder instance and passing your tokenizer as a parameter. 

```php
$newAnalyzer->tokenizer($tokenizer);
```

## Word Boundaries
The **Word Boundaries** tokenizer will produce a token each time it encounters a **word boundary**. 
```php
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;

$newAnalyer->tokenizer(new WordBoundaries(name: 'word_boundaries_tokenizer', maxTokenLength: 255));

// OR

$newAnalyer->tokenizeOnWordBoundaries(maxTokenLength: 255);
```

You may also pass the `maxTokenLength` parameter if you want to keep your tokens under a specific length. By default, this value is set to `255`.

Here is an example of the **Word Boundaries** Tokenizer.

```php
 "Aw shucks, pluto. I can’t be mad at ya!"
 -----------------------------------------
 Word Boundaries                          
 -----------------------------------------
 "Aw"                                     
 "shucks"                                 
 "pluto"                                  
 "I"                                      
 "can’t"                                  
 "be"                                     
 "mad"                                    
 "at"                                     
 "ya"                                     
```

## Whitespace
The **Whitespace** tokenizer produces a token each time it encounters a **whitespace**.

```php
use Sigmie\Index\Analysis\Tokenizers\Whitespace;

$newAnalyer->tokenizer(new Whitespace(name: 'whitespace_tokenizer'));

// OR

$newAnalyer->tokenizeOnWordBoundaries();
```

Here is an example of the **Whitespace** Tokenizer.

```php
 "Aw shucks, pluto. I can’t be mad at ya!" 
 -----------------------------------------
 Whitespace                               
 -----------------------------------------
 "Aw"                                     
 "shucks,"                                 // [tl! highlight]
 "pluto."                                  // [tl! highlight]
 "I"                                      
 "can’t"                                  
 "be"                                     
 "mad"                                    
 "at"                                     
 "ya!"                                     // [tl! highlight]
```

## Noop

The **Noop** tokenizer produces one single token.

```php
use Sigmie\Index\Analysis\Tokenizers\Noop;

$newAnalyzer->tokenizer(new Noop(name: 'noop_tokenizer'));

// OR

$newAnalyzer->dontTokenize();
```

Here is an example of the  **Noop** tokenizer:
```php
 "If you ain’t scared, you ain’t alive." 
 --------------------------------------- 
 Noop                                    
 --------------------------------------- 
 "If you ain’t scared, you ain’t alive." 
```

## Pattern
The **Pattern** tokenizer produces a token **after each** match of the passed Regular Expression pattern.
```php
use Sigmie\Index\Analysis\Tokenizers\Pattern;

$newAnalyzer->tokenizer(new Pattern(name: 'pattern_tokenizer', ','));

// OR

$newAnalyzer->tokenizeOnPattern(',')
```

Here is an example of the tokens of the **Pattern** tokenizer for the `,` pattern.
```php
 "Though at times it may feel like the sky is falling around you, never give up, for every day is a new day" 
 ----------------------------------------------------------------------------------------------------------- 
 Pattern  ","                                                                                                
 ----------------------------------------------------------------------------------------------------------- 
 "Though at times it may feel like the sky is falling around you"                                            
 " never give up"                                                                                            
 " for every day is a new day"                                                                               
```

## Simple pattern
The **Pattern** tokenizer produces a token **for each** match Regular Expression pattern that you pass.
```php
use Sigmie\Index\Analysis\Tokenizers\SimplePattern;

$newAnalyzer->tokenizer(new SimplePattern(name: 'simple_pattern_tokenizer', "'.*'"))

// OR

$newAnalyzer->tokenizeOnPatternMatch("'.*'");
```

Below is an example of how the produced token looks like for the `'.*'` pattern.

```php
 "I remember daddy told me 'Fairytales can come true'." 
 ------------------------------------------------------ 
 Pattern Match  "'.*'"                                  
 ------------------------------------------------------ 
 "'Fairytales can come true'"                           
```

**As you can see here, the only produced token is the one inside the quotes**

## Path hierarchy
The **Path** tokenizer creates **path** tokens for each  `delimiter` that you pass.
```php
use Sigmie\Index\Analysis\Tokenizers\PathHierarchy;

$newAnalyzer->tokenizer(new PathHierarchy(delimiter: '/'))

// OR

$newAnalyzer->tokenizePathHierarchy(delimiter: '/');
```
The **default** delimiter is `/`.

Below is an example of how the produced tokens look like: 
```php
 "Disney\Movies\Musical\Sleeping Beauty" 
 --------------------------------------- 
 Path hierarchy                          
 --------------------------------------- 
 "Disney"                                
 "Disney/Movies"                         
 "Disney/Movies/Musical"                 
 "Disney/Movies/Musical/Sleeping Beauty" 
```

## Non Letter
The **Non Letter** tokenizer produces a token each time if encounters a symbol that’s **NOT** a letter.
```php
use Sigmie\Index\Analysis\Tokenizers\NonLetter;

$newAnalyzer->tokenizer(new NonLetter)

// OR

$newAnalyzer->tokenizeOnNonLetter();
```

Bellow is an example of the **Non Letter** tokenizer:
```php
 "To infinity … and beyond!" 
 --------------------------- 
 Non Letter                  
 --------------------------- 
 "To"                        
 "infinity"                  
 "and"                       
 "beyond"
```
