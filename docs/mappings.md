---
title: Mappings & Properties
short_description: Reference for each Sigmie property type, with its Elasticsearch mapping, generated queries, and accepted values, including nested and semantic fields.
keywords: [mappings, properties, field types, text, keyword, number, date, nested, schema]
category: Core Concepts
order: 4
related_pages: [document, index, search, analysis]
---

# Mappings & Properties

`NewProperties` builds the schema of an index. Each method adds one field and returns it, so you can chain modifiers onto it:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->name('director');
$props->number('year')->integer();
```

Pass the same instance to `newIndex()`, `collect()`, and `newSearch()`. [Core Concepts](core-concepts.md#properties-do-three-jobs) explains what Sigmie does with it at each point.

The type tables below use these columns:

- Default name is the field name when you call the method without one.
- Mapping is the Elasticsearch field type, its analysis, and its sub-fields.
- Queries are the queries `newSearch()` runs on the field for a query string.
- Accepts lists the values that pass the check in a collection with properties. Sigmie checks each item of a list on its own.

## High-level types

High-level types bundle a mapping with the queries that suit one kind of data.

| Method | Default name | Mapping | Queries | Accepts |
|--------|--------------|---------|---------|---------|
| `title()` | `title` | `text`, word tokens, lowercase. Sub-fields `.keyword`, `.sortable`. | `match_phrase_prefix`, `match` | string |
| `name()` | `name` | `text`, 4 to 5 character n-grams, index prefixes. Sub-fields `.keyword`, `.sortable`, and `.{name}_text` for prefixes. | `match` on n-grams, `match_phrase_prefix` and `match_bool_prefix` on `.{name}_text` | string |
| `category()` | `category` | `text`. Sub-fields `.keyword`, `.sortable`. | `match`, `term` on `.keyword`, `match_phrase_prefix` | string |
| `tags()` | `tags` | `text`, `#` removed, word tokens, lowercase. | `match`, `match_phrase_prefix` | string |
| `price()` | `price` | `scaled_float`, scaling factor 100. | none | number |
| `longText()` | none | `text`, no sub-fields. | `match` | string |
| `shortText()` | none | Same as `longText()`. | `match` | string |
| `html()` | none | `text`, HTML tags stripped, word tokens, trimmed, lowercase, repeated tokens removed. | `match` | string |
| `searchableNumber()` | none | `text`, every non-digit removed. Sub-fields `.keyword`, `.sortable`. | `match`, `match_phrase_prefix` | string |
| `id()` | none | `keyword`, case-sensitive. Sub-field `.sortable` (integer). | `term`, `prefix` | integer |
| `email()` | `email` | `text`, split on `@` and `.`, lowercase, index prefixes. Sub-fields `.keyword`, `.sortable`. | `match`, `prefix`, `term` on `.keyword` | string |
| `address()` | `address` | `text`, word tokens, lowercase, index prefixes. Sub-fields `.keyword`, `.sortable`. | `match_bool_prefix` | string |
| `caseSensitiveKeyword()` | none | `keyword`, no lowercase normalizer. | `term`, `prefix` | string |
| `path()` | none | `text`, one token per level (`/a`, `/a/b`, `/a/b/c`), lowercase, index prefixes. Sub-field `.keyword`. | `prefix`, `match` | string |
| `boost()` | `boost` | `float`. | none | number |

```php
$props->title('title');
$props->name('director');
$props->category('genre');
$props->tags('skills');
$props->price();                       // field name 'price'
$props->longText('synopsis');
$props->html('content');
$props->searchableNumber('phone');
$props->id('user_id');
$props->email('contact_email');
$props->caseSensitiveKeyword('promo_code');
$props->path('file_path');
$props->boost();
```

`boost()` multiplies the score of each hit by the document's value of the field. A document without a value keeps its score.

`searchableNumber()` stores the number as text, so users find it by typing it. To filter, sort, or aggregate numbers, use `number()`.

### Facet logic

Every type that supports facets, such as `category()`, `keyword()`, and `price()`, takes a facet logic. The default is conjunctive:

```php
$props->category('brand')->facetDisjunctive();   // OR between selected values
$props->category('color')->facetConjunctive();   // AND between selected values
```

[Facets](facets.md) explains both.

## Native types

Native types map one to one to an Elasticsearch field type.

| Method | Mapping | Queries | Accepts |
|--------|---------|---------|---------|
| `text()` | `text` with the index default analyzer. | `match` | string |
| `keyword()` | `keyword` with a lowercase normalizer. | `term`, `prefix` | string |
| `number()` | `integer` until you call a type modifier. | none | number |
| `integer()`, `float()`, `long()`, `double()`, `scaledFloat()` | Shortcuts for `number()` with that type. | none | number |
| `bool()` | `boolean` | `term` for `true` when the query string equals the field name | `true` or `false` |
| `date()` | `date`, format `strict_date_optional_time_nanos`. | none | `Y-m-d` string |
| `datetime()` | `date`, format `strict_date_optional_time_nanos`. | none | timestamp string, see [Date and datetime](#date-and-datetime) |
| `geoPoint()` | `geo_point` | none | `['lat' => …, 'lon' => …]`, or a list of them |
| `range()` | `integer_range` until you call a type modifier. | none | array with at least one of `gte`, `gt`, `lte`, `lt` |

To filter by distance from a `geoPoint()` field, see [Geo-location filtering](filter-parser.md#geo-location-filtering).

### Text modifiers

```php
$props->text('category')->keyword();                    // adds .keyword
$props->text('category')->keyword()->makeSortable();    // adds .keyword and .sortable
$props->text('name')->searchAsYouType();                // search_as_you_type mapping
$props->text('suggest')->completion();                  // completion mapping
$props->text('content')->indexPrefixes();               // index_prefixes, 1 to 10 characters
$props->text('content')->indexPrefixes(2, 5);           // index_prefixes, 2 to 5 characters
$props->text('description')->unstructuredText();        // text mapping, the default
```

`keyword()` works only on a `text` mapping. On a `search_as_you_type` or `completion` field it throws an exception. `makeSortable()` returns nothing, so call it last.

A `searchAsYouType()` field runs `multi_match` over the field and its `._2gram` and `._3gram` sub-fields. A `completion()` field feeds [completion suggestions](search.md#suggest-completions).

### Number modifiers

```php
$props->number('count')->integer();
$props->number('rating')->float();
$props->number('views')->long();
$props->number('ratio')->double();
$props->number('amount')->scaledFloat();       // scaling factor 100
$props->number('amount')->scaledFloat(1000);
```

### Range modifiers

```php
$props->range('age_range');                    // integer_range
$props->range('price_range')->float();         // float_range
$props->range('population')->long();           // long_range
$props->range('score')->double();              // double_range
$props->range('availability')->date();         // date_range
$props->range('subnet')->ip();                 // ip_range
```

### Date and datetime

Both methods create an Elasticsearch `date` field that accepts ISO 8601 values. They differ in the check that a collection with properties runs:

| Method | Accepted strings | `DateTime` values become |
|--------|------------------|--------------------------|
| `date()` | `Y-m-d` | `Y-m-d` |
| `datetime()` | `Y-m-d\TH:i:s.uP`, `Y-m-d\TH:i:s.u\Z`, `Y-m-d\TH:i:s.uO`, `Y-m-d\TH:i:s.u`, `Y-m-d\TH:i:sP`, `Y-m-d\TH:i:s\Z`, `Y-m-d\TH:i:sO`, `Y-m-d\TH:i:s` | `Y-m-d\TH:i:s.uP` |

```php
$props->date('release_date');          // '2023-04-07'
$props->datetime('created_at');        // '2023-04-07T12:38:29.000000Z'
```

To add an Elasticsearch date pattern to the mapping, call `format()`. It returns nothing, so call it last:

```php
$props->date('created_at')->format('MM/dd/yyyy');
```

> **Note:** `format()` changes only the mapping. A collection with properties still checks values against the formats in the table above.

## Object and nested types

`object()` and `nested()` take a callback that defines the inner fields. Sigmie searches each inner field with the queries of its own type.

```php
$props->object('director', function (NewProperties $props) {
    $props->name('name');
    $props->number('birth_year')->integer();
    $props->email('contact');
});

$props->nested('cast', function (NewProperties $props) {
    $props->name('actor');
    $props->keyword('character');
    $props->number('screen_time')->integer();
});
```

| Method | Mapping | Use for | Filter syntax |
|--------|---------|---------|---------------|
| `object()` | `object`. Elasticsearch flattens the inner fields. | One object per document. | `director.name:"Nolan"` |
| `nested()` | `nested`. Each item is indexed on its own. | A list of objects whose fields belong together. | `cast:{actor:"Keanu Reeves" AND character:"Neo"}` |

With `object()`, a filter on a list of objects can match `actor` from one item and `character` from another. `nested()` matches both inside one item. The Filter Parser covers [object fields](filter-parser.md#object-fields) and [nested field filtering](filter-parser.md#nested-field-filtering).

## Semantic fields

`semantic()` adds a vector field to a `text` field. Sigmie generates the embeddings when a collection with properties writes the document. It returns the semantic field, so you can chain vector options onto it:

```php
use Sigmie\Enums\VectorSimilarity;

$props->text('description')->semantic(api: 'embeddings', accuracy: 3, dimensions: 384);

$props->text('content')
    ->semantic(api: 'embeddings', dimensions: 512)
    ->similarity(VectorSimilarity::Euclidean);
```

| Parameter | Default | Values |
|-----------|---------|--------|
| `api` | none | The name passed to `$sigmie->registerApi()`. |
| `accuracy` | `3` | `1` to `7`. `1` makes one embedding of the whole value. `2` to `6` embed each chunk and average the vectors, with a denser HNSW graph at each level. `7` keeps every chunk vector and scores them with a script, without a vector index. |
| `dimensions` | `256` | `128`, `256`, `384`, `512`, `1024`, `1536`, `2048`, or `3072`. |

| Option | Default | Values |
|--------|---------|--------|
| `similarity()` | `VectorSimilarity::Cosine` | `Cosine`, `DotProduct`, `Euclidean`, `MaxInnerProduct`. Shortcuts: `cosineSimilarity()`, `dotProductSimilarity()`, `euclideanSimilarity()`, `maxInnerProductSimilarity()`. |
| `indexType()` | `hnsw` | `hnsw`, `int8_hnsw`, `int4_hnsw`, `bbq_hnsw`, `flat`, `int8_flat`, `int4_flat`, `bbq_flat`. |

Register the embeddings API under the name that `api` uses:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$sigmie->registerApi('embeddings', new OpenAIEmbeddingsApi('sk-...'));
```

To add a second vector to the same field, call `semantic()` again on the field:

```php
$description = $props->text('job_description');
$description->semantic(api: 'embeddings', accuracy: 3, dimensions: 512);
$description->semantic(api: 'embeddings', accuracy: 5, dimensions: 512)->euclideanSimilarity();
```

[Semantic Search](semantic-search.md) shows how to search these fields.

## Custom analyzer for one field

`withNewAnalyzer()` gives one `text` field its own analyzer:

```php
use Sigmie\Index\NewAnalyzer;

$props->text('email')
    ->withNewAnalyzer(function (NewAnalyzer $analyzer) {
        $analyzer->tokenizeOnPattern('(@|\.)');
        $analyzer->lowercase();
    });
```

## Custom queries for one field

`withQueries()` replaces the queries that `newSearch()` runs on a field. The callback receives the query string and returns a list of queries:

```php
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;

$props->text('email')
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

To reuse a field definition, extend `Text` and add it with `type()`. Sigmie calls three methods:

| Method | Called | Purpose |
|--------|--------|---------|
| `configure()` | In the constructor. | Set the mapping and the sub-fields. |
| `analyze()` | When Sigmie builds the analyzers. Only on classes that implement `Analyze`. | Define the field analyzer. |
| `queries()` | For each search with a query string. | Return the queries for the field. |

```php
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\Contracts\Analyze;
use Sigmie\Mappings\Types\Text;
use Sigmie\Query\Queries\Term\Prefix;
use Sigmie\Query\Queries\Term\Term;
use Sigmie\Query\Queries\Text\Match_;

class Color extends Text implements Analyze
{
    public function configure(): void
    {
        $this->unstructuredText()->indexPrefixes()->keyword();
    }

    public function analyze(NewAnalyzer $analyzer): void
    {
        $analyzer->tokenizeOnWhitespaces();
        $analyzer->lowercase();
    }

    public function queries(array|string $queryString): array
    {
        return [
            new Match_($this->name(), $queryString),
            new Prefix($this->name(), $queryString),
            new Term("{$this->name()}.keyword", $queryString),
        ];
    }
}

$props->type(new Color('color')); // [tl! highlight]
```

To ship field types in a package, see [Extending Sigmie](extending.md).

## Inspect properties

`get()` returns the built `Properties`:

```php
$properties = $props->get();

$properties->fieldNames();          // ['title', 'cast.actor', ...]
$properties->fieldNames(true);      // also lists 'cast' and other parent fields
```

Each field checks a value with `validate()`. It returns a pass flag and a message:

```php
[$valid, $message] = $properties['release_date']->validate('release_date', '2023-04-07');
```
