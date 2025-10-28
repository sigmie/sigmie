---
title: Language Support
short_description: Configure language-specific analyzers and filters
keywords: [languages, multilingual, greek, german, stemming, stopwords]
category: Text Analysis
order: 5
related_pages: [analysis, token-filters, tokenizers]
---

# Languages

```php
use Sigmie\Greek\Greek;

        /** @var GreekBuilder */
$greekBuilder = $this->sigmie
        ->newIndex($alias)
        ->language(new Greek())
            ->greekLowercase()
            ->greekStemmer()
            ->greekStopwords();
```

```php
use Sigmie\German\German;

        $germanBuilder
            ->germanLightStemmer()
            ->germanStemmer()
            ->germanStemmer2()
            ->germanMinimalStemmer()

            ->germanNormalize()
            ->germanStopwords()
            ->germanLowercase()

            ->create();

```

```php
use Sigmie\English\English;

        /** @var EnglishBuilder */
        $englishBuilder = $this->sigmie->newIndex($alias)->language(new English());

        $englishBuilder

            ->englishStemmer()
            ->englishPorter2Stemmer()
            ->englishLovinsStemmer()
            ->englishLightStemmer()
            ->englishPossessiveStemming()
            ->englishMinimalStemmer()

            ->englishStopwords()
            ->englishLowercase()
            ->create();

```
