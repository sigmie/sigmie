---
title: Mappings & Properties
short_description: Define your Elasticsearch schema with Sigmie's high-level and native field types — title, name, category, price, semantic vectors, nested, and more.
keywords: [mappings, properties, field types, text, keyword, number, schema]
category: Core Concepts
order: 4
related_pages: [document, index, search, analysis]
---

# Mappings & Properties

Properties tell Elasticsearch what each field is and how to search it. Sigmie exposes both **native Elasticsearch types** (`text`, `keyword`, `number`, `bool`, `date`, `geoPoint`) and **high-level types** (`title`, `name`, `category`, `price`, `email`) that wrap the natives with sensible defaults.

You build mappings with the `NewProperties` builder and pass the same instance to your index, your collection, and your searches:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->name('director');
$props->number('year')->integer();

$sigmie->newIndex('movies')->properties($props)->create();
$sigmie->collect('movies')->properties($props);
$sigmie->newSearch('movies')->properties($props)->queryString('matrix')->get();
```

Reusing the same `$props` is what lets Sigmie generate the right queries for each field.

## High-level types

### Title

For short, searchable text — movie titles, article titles, product names.

```php
$props->title('name');
```

### Name

For personal and place names. Tuned for autocomplete-style matching.

```php
$props->name('director');
$props->name('first_name');
$props->name('city');
```

### Category

For exact-match classification — genres, departments, brands.

```php
$props->category('genre');
$props->category('brand');
```

Categories can opt into faceting (see [Facets](facets.md)):

```php
$props->category('brand')->facetDisjunctive();
$props->category('color')->facetConjunctive();
```

### Tags

For multi-value fields — product tags, attributes, skills.

```php
$props->tags('skills');
```

### Price

For monetary values — supports range queries and histograms.

```php
$props->price();             // defaults to field name 'price'
$props->price('amount');
```

### Long text

For descriptions, summaries, comments, articles.

```php
$props->longText('description');
$props->longText('synopsis');
```

### Short text

For brief, single-line text content.

```php
$props->shortText('headline');
```

### HTML

Strips HTML tags before indexing. Useful for crawled content.

```php
$props->html('content');
```

### Searchable number

Numbers users actually type into a search box — years, phone numbers, reservation IDs.

```php
$props->searchableNumber('birth_year');
$props->searchableNumber('phone');
```

Reach for `number()` instead when you're filtering or sorting numerically but not searching.

### Identifier

For primary and foreign keys — filterable, groupable.

```php
$props->id('user_id');
$props->id('product_id');
```

### Email and address

```php
$props->email('contact_email');
$props->address('shipping_address');
```

### Case-sensitive keyword

Keyword fields lowercase their values by default. Use this when case matters:

```php
$props->caseSensitiveKeyword('promo_code');
```

### Path

For hierarchical paths, indexed at each level (`/a`, `/a/b`, `/a/b/c`):

```php
$props->path('file_path');
```

### Boost

For per-document score boosts:

```php
$props->boost();
```

### Autocomplete

For prefix-style suggestions:

```php
$props->autocomplete();
```

## Native types

### Text

The Elasticsearch workhorse for unstructured text:

```php
$props->text('description');
```

Add a `.keyword` sub-field if you need to sort, filter, or aggregate on the same field:

```php
$props->text('category')->keyword();          // can search AND filter
$props->text('category')->keyword()->makeSortable();
```

Other modifiers:

```php
$props->text('name')->searchAsYouType();      // search-as-you-type
$props->text('content')->indexPrefixes();     // index prefixes for Prefix queries
$props->text('description')->unstructuredText();   // explicit (it's the default)
```

### Keyword

Stored as-is, no analysis. Use for exact matching, sorting, and aggregations:

```php
$props->keyword('ISBN');
$props->keyword('status');
```

### Number

```php
$props->number('rating')->float();
$props->number('count')->integer();
$props->number('amount')->double();
$props->number('precise_amount')->scaledFloat();
```

There are also convenience methods:

```php
$props->integer('count');
$props->float('rating');
$props->long('big_number');
$props->double('high_precision');
```

### Boolean

```php
$props->bool('is_active');
```

### Date

```php
$props->date('created_at');
```

Default format is the PHP ISO format (`Y-m-d\TH:i:s.uP`). Format dates with:

```php
(new DateTime())->format('Y-m-d\TH:i:s.uP');
```

Supported out of the box:

- `2023-04-07T12:38:29.000000Z`
- `2023-04-07T12:38:29Z`
- `2023-04-07T12:38:29`
- `2023-04-07`
- `2023-04-07T12:38:29.000000+02:00`
- `2023-04-07T12:38:29+02:00`

For other formats, pass an Elasticsearch date pattern:

```php
$props->date('created_at', 'MM/dd/yyyy');
```

### Geo point

```php
$props->geoPoint('location');
```

Documents store coordinates as `['lat' => 12.34, 'lon' => 56.78]`. See [Filter Parser](filter-parser.md#geo-location-filtering) for proximity filters.

## Complex types

### Object

For single nested objects (not arrays). Fields are indexed flatly:

```php
$props->object('director', function (NewProperties $props) {
    $props->name('name');
    $props->number('birth_year')->integer();
    $props->email('contact');
});
```

Filter with dot notation: `director.name:"Nolan"`.

### Nested

For arrays of objects where you need to preserve the relationship between sibling values:

```php
$props->nested('cast', function (NewProperties $props) {
    $props->name('actor');
    $props->keyword('character');
    $props->number('screen_time')->integer();
});
```

Filter with curly braces: `cast:{actor:"Keanu Reeves" AND character:"Neo"}`.

Use `object()` when each document has one of these things; use `nested()` when each document has a list and the fields within each item belong together.

## Semantic fields

Make any text field semantic by chaining `->semantic()`. Sigmie generates vector embeddings at index time using whichever embeddings API you registered:

```php
$props->text('description')->semantic(api: 'embeddings', dimensions: 384);
```

The `api` name matches what you passed to `Sigmie::registerApi()`:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
```

Tune accuracy (1 = fast, 5 = high quality):

```php
$props->text('content')->semantic(
    api: 'embeddings',
    dimensions: 512,
    accuracy: 3,
);
```

Choose a similarity metric:

```php
use Sigmie\Enums\VectorSimilarity;

$props->text('content')->semantic(
    api: 'embeddings',
    similarity: VectorSimilarity::Cosine,           // default
);
```

Add multiple vector representations of the same field:

```php
$props->text('job_description')
    ->semantic(api: 'embeddings', accuracy: 3, dimensions: 512)
    ->semantic(api: 'embeddings', accuracy: 5, dimensions: 512,
        similarity: VectorSimilarity::Euclidean);
```

See [Semantic Search](semantic-search.md) for the full feature.

## Custom analyzers

Override analysis on a per-field basis:

```php
use Sigmie\Index\NewAnalyzer;

$props->text('email')
    ->withNewAnalyzer(function (NewAnalyzer $analyzer) {
        $analyzer->tokenizeOnPattern('(@|\.)');
        $analyzer->lowercase();
    });
```

## Custom query logic

Define which queries to run for a given field:

```php
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;

$props->text('email')
    ->unstructuredText()
    ->indexPrefixes()
    ->keyword()
    ->withQueries(function (string $queryString) {
        return [
            new Match_('email', $queryString),
            new Prefix('email', $queryString),
            new Term('email.keyword', $queryString),
        ];
    });
```

## Custom property classes

For reusable types, extend a base class:

```php
use Sigmie\Mappings\Types\Text;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;

class Color extends Text
{
    public string $name = 'color';

    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function analyze(NewAnalyzer $analyzer): void
    {
        $analyzer->tokenizeOnWhitespaces();
        $analyzer->lowercase();
    }

    public function queries(string $queryString): array
    {
        return [
            new Match_($this->name, $queryString),
            new Prefix($this->name, $queryString),
            new Term("{$this->name}.keyword", $queryString),
        ];
    }
}
```

Register the type:

```php
$props->type(new Color);
```

For shipping field types as a reusable package, see [Extending Sigmie](extending.md).

## Inspect properties

```php
$properties = $props->get();
$fields = $properties->fieldNames();          // ['title', 'cast.actor', ...]
$allFields = $properties->fieldNames(true);   // include intermediate objects
```

Validate a value against a property:

```php
[$valid, $message] = $properties['created_at']->validate('created_at', '2023-04-07');
```

## Quick reference

**Native types:** `text`, `keyword`, `number`, `bool`, `date`, `geoPoint`

**High-level types:** `title`, `name`, `category`, `tags`, `price`, `longText`, `shortText`, `html`, `email`, `address`, `searchableNumber`, `id`, `caseSensitiveKeyword`, `path`, `boost`, `autocomplete`

**Complex types:** `object`, `nested`

**Semantic:** `->semantic(api:, dimensions:, accuracy:, similarity:)`
