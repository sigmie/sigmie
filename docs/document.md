---
title: Documents
short_description: Create, index, and iterate over documents
keywords: [documents, indexing, upsert, bulk operations, collections]
category: Core Concepts
order: 3
related_pages: [index, mappings, search]
---

# Documents

A `Document` is a JSON object stored in an index. Sigmie treats every index as a writable collection: you add `Document` instances, iterate over them, query them, and update them.

## Create documents

```php
use Sigmie\Document\Document;

$doc = new Document(['title' => 'The Matrix', 'year' => 1999]);
```

Documents can hold any JSON-serializable structure:

```php
$doc = new Document([
    'title' => 'Inception',
    'director' => [
        'name' => 'Christopher Nolan',
        'born' => 1970,
    ],
    'cast' => [
        ['name' => 'Leonardo DiCaprio', 'role' => 'Cobb'],
        ['name' => 'Marion Cotillard', 'role' => 'Mal'],
    ],
    'metadata' => [
        'runtime' => 148,
        'budget' => 160_000_000,
    ],
]);
```

### Custom document IDs

Pass an ID as the second argument:

```php
$doc = new Document(['title' => 'The Matrix'], 'matrix_1999');
```

Custom IDs let you re-index a document later by writing the same ID — Elasticsearch overwrites it in place.

## Get a collection

```php
$movies = $sigmie->collect('movies');
```

For tests, `refresh: true` makes documents immediately searchable:

```php
$movies = $sigmie->collect('movies', refresh: true);
```

> **Warning:** Don't use `refresh: true` in production. It forces a costly refresh on every write.

## Add documents

A single document:

```php
$movies->add(new Document(['title' => 'Mickey Mouse']));
```

Many documents (much faster than calling `add()` in a loop):

```php
$movies->merge([
    new Document(['title' => 'Snow White']),
    new Document(['title' => 'Cinderella']),
    new Document(['title' => 'Sleeping Beauty']),
]);
```

## Validate with properties

Pass properties to the collection and Sigmie validates each document against the schema before indexing:

```php
use Sigmie\Mappings\NewProperties;

$props = new NewProperties;
$props->title('title');
$props->date('release_date');
$props->number('rating')->float();

$movies = $sigmie->collect('movies')->properties($props);

$movies->merge([
    new Document([
        'title' => 'The Matrix',
        'release_date' => '1999-03-31T00:00:00Z',
        'rating' => 8.7,
    ]),
]);
```

Invalid data (a non-numeric `rating`, an unparseable `release_date`) is caught at indexing time.

## Update a document

To update, write the same ID:

```php
$movies->add(new Document([
    'title' => 'The Matrix',
    'year' => 1999,
    'rating' => 8.7,
], 'matrix_1'));
```

Elasticsearch indexes the new version under the same `_id`, replacing the previous one.

## Iterate over a collection

`each()` streams every document without loading the index into memory. Sigmie pages through results internally using a Point-in-Time and `search_after`, so writes during iteration don't corrupt the cursor.

```php
$movies->each(function (Document $doc): void {
    echo $doc['title'] . "\n";
});
```

The default page size is 500. Override it with `chunk()`:

```php
$movies->chunk(100)->each(function (Document $doc): void {
    processOne($doc);
});
```

For iteration over a **subset** (filtered, sorted), use `NewSearch::each()` or `NewSearch::lazy()` instead. See [Iterating over all matching hits](search.md#iterating-over-all-matching-hits).

## Other collection methods

```php
$movies->count();                       // total document count
$movies->has('matrix_1');               // does this ID exist
$movies->get('matrix_1');               // fetch one by ID
$movies->getMany(['matrix_1', 'inception_1']);  // fetch many by ID
$movies->random(5);                     // 5 random documents
$movies->remove('matrix_1');            // delete by ID
$movies->clear();                       // delete every document
$movies->toArray();                     // load all into memory (small indices only)
```

`Document` implements `ArrayAccess`:

```php
$doc['title'] = 'New Title';
$title = $doc['title'];
isset($doc['year']);
unset($doc['description']);
```

## Complex data types

### Dates

```php
$props->date('created_at');

new Document([
    'title' => 'New Movie',
    'created_at' => '2023-04-07T12:38:29.000000Z',
]);
```

### Geo points

```php
$props->geoPoint('location');

new Document([
    'venue' => 'Cinema Downtown',
    'location' => ['lat' => 40.7128, 'lon' => -74.0060],
]);
```

### Nested arrays of objects

```php
$props->nested('cast', function (NewProperties $props) {
    $props->name('actor');
    $props->keyword('role');
});

new Document([
    'title' => 'Avengers',
    'cast' => [
        ['actor' => 'Robert Downey Jr.', 'role' => 'Iron Man'],
        ['actor' => 'Chris Evans', 'role' => 'Captain America'],
    ],
]);
```

Nested fields preserve the relationship between sibling values during search — see [Filter Parser](filter-parser.md#nested-field-filtering) for nested filtering syntax.

## When are writes visible to search

By default Elasticsearch operates in "near real-time" — writes become searchable about a second later:

```php
$movies = $sigmie->collect('movies');
$movies->add(new Document(['title' => 'Snow White']));
$movies->count();   // 0 (immediately)
sleep(1);
$movies->count();   // 1
```

`refresh: true` makes them visible immediately:

```php
$movies = $sigmie->collect('movies', refresh: true);
$movies->add(new Document(['title' => 'Snow White']));
$movies->count();   // 1
```

For batch processing, use the default and refresh once when you're done:

```php
$movies = $sigmie->collect('movies');
$movies->merge($manyDocuments);
$sigmie->index('movies')->refresh();
```
