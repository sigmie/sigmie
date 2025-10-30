---
title: API Reference
short_description: Complete reference for Sigmie classes and methods
keywords: [api reference, class reference, methods, documentation]
category: Reference
order: 3
related_pages: [api, packages]
---

# API Reference

This document provides a comprehensive reference for all Sigmie classes, methods, and their usage.

## Table of Contents

- [Sigmie Client](#sigmie-client)
- [Index Management](#index-management)
- [Document Management](#document-management)
- [Properties](#properties)
- [Search](#search)
- [Query Builder](#query-builder)
- [Collections](#collections)
- [Response Objects](#response-objects)

## Sigmie Client

The main client class for interacting with Elasticsearch.

### Class: `Sigmie\Sigmie`

#### Static Methods

##### `create(array $hosts, array $config = []): Sigmie`

Creates a new Sigmie client instance.

```php
$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: ['connect_timeout' => 15]
);
```

**Parameters:**
- `$hosts` - Array of Elasticsearch host URLs
- `$config` - Guzzle HTTP client configuration options

#### Instance Methods

##### `newIndex(string $name): NewIndex`

Creates a new index builder.

```php
$index = $sigmie->newIndex('movies');
```

##### `index(string $name): Index`

Gets an existing index instance.

```php
$index = $sigmie->index('movies');
```

##### `collect(string $indexName, bool $refresh = false): Collection`

Creates a document collection for an index.

```php
$collection = $sigmie->collect('movies');
$liveCollection = $sigmie->collect('movies', refresh: true);
```

##### `newSearch(string $indexName): NewSearch`

Creates a new search builder.

```php
$search = $sigmie->newSearch('movies');
```

##### `newQuery(string $indexName): NewQuery`

Creates a new query builder.

```php
$query = $sigmie->newQuery('movies');
```

## Index Management

### Class: `Sigmie\Index\NewIndex`

Builder for creating new indices.

#### Methods

##### `properties(NewProperties $properties): self`

Sets the index properties/mappings.

```php
$index->properties($properties);
```

##### `create(): Index`

Creates the index in Elasticsearch.

```php
$createdIndex = $index->create();
```

##### `shards(int $shards): self`

Sets the number of primary shards.

```php
$index->shards(3);
```

##### `replicas(int $replicas): self`

Sets the number of replica shards.

```php
$index->replicas(1);
```

##### Analysis Methods

```php
$index->lowercase();                    // Convert text to lowercase
$index->uppercase();                    // Convert text to uppercase  
$index->tokenizeOnWhitespaces();       // Split on whitespace
$index->tokenizeOnWordBoundaries();    // Split on word boundaries
$index->trim();                        // Remove leading/trailing spaces
$index->dontTokenize();                // Don't split text
```

##### Language Methods

```php
$index->language(Language $language);   // Set language analyzer
$index->germanNormalize();              // German text normalization
```

##### Advanced Configuration

```php
$index->config(string $key, $value);    // Set custom index setting
$index->autocomplete(array $fields);    // Enable autocomplete
```

### Class: `Sigmie\Index\Index`

Represents an existing index.

#### Properties

```php
$index->name;         // Index name
$index->mappings;     // Index mappings
$index->raw;          // Raw Elasticsearch response
```

#### Methods

##### `update(callable $callback): Index`

Updates the index configuration.

```php
$index->update(function(UpdateIndex $updateIndex) {
    $updateIndex->properties($newProperties);
});
```

##### `delete(): void`

Deletes the index.

```php
$index->delete();
```

##### `analyze(string $text): array`

Analyzes text using the index analyzer.

```php
$tokens = $index->analyze('The Matrix');
```

##### `refresh(): void`

Manually refreshes the index.

```php
$index->refresh();
```

## Document Management

### Class: `Sigmie\Document\Document`

Represents a document to be indexed.

#### Constructor

```php
new Document(array $data, string $id = null)
```

**Parameters:**
- `$data` - Document data as associative array
- `$id` - Optional custom document ID

#### Examples

```php
// Auto-generated ID
$doc = new Document(['title' => 'The Matrix']);

// Custom ID  
$doc = new Document(['title' => 'The Matrix'], 'matrix_1999');

// Complex document
$doc = new Document([
    'title' => 'The Matrix',
    'year' => 1999,
    'cast' => ['Keanu Reeves', 'Laurence Fishburne'],
    'director' => [
        'name' => 'The Wachowskis',
        'previous_films' => ['Bound']
    ]
]);
```

#### Array Access

Documents implement ArrayAccess:

```php
$doc['title'] = 'New Title';
$title = $doc['title'];
isset($doc['year']);
unset($doc['description']);
```

## Properties

### Class: `Sigmie\Mappings\NewProperties`

Builder for defining document field mappings.

#### Native Elasticsearch Types

##### Text Fields

```php
$props->text(string $field);                    // Full-text search field
$props->keyword(string $field);                 // Exact value field
```

##### Numeric Fields

```php
$props->number(string $field);                  // Returns NumberProperty
```

**NumberProperty Methods:**
```php
->integer();                                     // Integer numbers
->float();                                       // Floating point numbers
```

##### Other Basic Types

```php
$props->bool(string $field);                    // Boolean field
$props->date(string $field, string $format = null); // Date field
$props->geoPoint(string $field);               // Geographic coordinates
```

#### High-Level Semantic Types

```php
$props->name(string $field = 'name');          // Names (people, places)
$props->title(string $field = 'title');        // Titles (movies, books)
$props->shortText(string $field);              // Short text content
$props->longText(string $field);               // Long text content
$props->html(string $field);                   // HTML content (strips tags)
$props->email(string $field);                  // Email addresses
$props->address(string $field);                // Physical addresses
$props->category(string $field = 'category');  // Categories/classifications
$props->tags(string $field);                   // Tag collections
$props->price(string $field);                  // Monetary values
$props->searchableNumber(string $field);       // Numbers that can be searched
$props->id(string $field);                     // Identifier fields
$props->caseSensitiveKeyword(string $field);   // Case-sensitive keywords
$props->path(string $field);                   // Hierarchical paths
$props->boost(string $field = 'boost');        // Document boost values
$props->autocomplete(string $field = 'autocomplete'); // Autocomplete field
```

#### Complex Types

##### Nested Fields

```php
$props->nested(string $field, callable $callback);
```

**Example:**
```php
$props->nested('cast', function (NewProperties $props) {
    $props->name('actor');
    $props->keyword('character');
    $props->number('screen_time')->integer();
});
```

##### Object Fields

```php
$props->object(string $field, callable $callback);
```

**Example:**
```php
$props->object('director', function (NewProperties $props) {
    $props->name('name');
    $props->number('birth_year')->integer();
});
```

#### Semantic Search

```php
$props->text('description')->semantic();        // Basic semantic search
$props->text('content')->semantic(              // Advanced configuration
    accuracy: 5,
    dimensions: 768,
    similarity: VectorSimilarity::Cosine
);
```

#### Field Configuration Methods

Most field types support additional configuration:

```php
$props->text('description')
    ->unstructuredText()                         // Mark as unstructured
    ->searchAsYouType()                         // Enable search-as-you-type
    ->indexPrefixes()                           // Index prefixes for prefix queries
    ->keyword()                                 // Also store as keyword
    ->makeSortable();                          // Make sortable

$props->keyword('category')
    ->makeSortable();                          // Enable sorting
```

#### Custom Property Types

```php
$props->type(PropertyInterface $customProperty);
```

#### Getting Properties

```php
$properties = $props->get();                    // Get Properties instance
$fieldNames = $properties->fieldNames();        // Get field names array
```

## Search

### Class: `Sigmie\Search\NewSearch`

High-level search builder with user-friendly features.

#### Required Methods

##### `properties(NewProperties $properties): self`

Sets the search properties.

```php
$search->properties($properties);
```

##### `queryString(string $query, int $weight = 1): self`

Sets the search query string.

```php
$search->queryString('matrix sci-fi');
$search->queryString('action', weight: 2);  // With custom weight
```

#### Query Configuration

##### `fields(array $fields): self`

Limits search to specific fields.

```php
$search->fields(['title', 'description']);
```

##### `retrieve(array $fields): self`

Specifies which fields to return.

```php
$search->retrieve(['title', 'year', 'rating']);
```

#### Search Features

##### `typoTolerance(int $oneTypoChars = 3, int $twoTypoChars = 6): self`

Enables typo tolerance.

```php
$search->typoTolerance();
$search->typoTolerance(oneTypoChars: 4, twoTypoChars: 8);
```

##### `typoTolerantAttributes(array $fields): self`

Specifies which fields allow typos.

```php
$search->typoTolerantAttributes(['title', 'director']);
```

##### `highlighting(array $fields, string $prefix = '<em>', string $suffix = '</em>'): self`

Enables result highlighting.

```php
$search->highlighting(['title', 'description']);
$search->highlighting(['title'], '<mark>', '</mark>');
```

##### `weight(array $weights): self`

Sets field importance weights.

```php
$search->weight(['title' => 3, 'description' => 1]);
```

##### `minScore(float $score): self`

Sets minimum score threshold.

```php
$search->minScore(2.0);
```

#### Filtering and Sorting

##### `filters(string $filters): self`

Adds filter conditions.

```php
$search->filters('year>1990 AND rating>=8.0');
$search->filters('is_available:true AND NOT category:"Horror"');
```

##### `sort(string $sort): self`

Sets result sorting.

```php
$search->sort('_score:desc year:desc');
$search->sort('rating:desc title:asc');
```

#### Pagination

##### `from(int $from): self`

Sets result offset.

```php
$search->from(20);  // Skip first 20 results
```

##### `size(int $size): self`

Sets result limit.

```php
$search->size(10);  // Return 10 results
```

#### Advanced Features

##### `facets(string $facets): self`

Enables faceted search.

```php
$search->facets('category');
$search->facets('price:100');  // Price intervals of 100
```

##### `semantic(): self`

Enables semantic search.

```php
$search->semantic();
```

##### `disableKeywordSearch(): self`

Disables keyword search (semantic only).

```php
$search->semantic()->disableKeywordSearch();
```

##### `autocompletePrefix(string $prefix): self`

Enables autocomplete functionality.

```php
$search->autocompletePrefix('mat');
```

##### `noResultsOnEmptySearch(): self`

Returns no results for empty queries.

```php
$search->noResultsOnEmptySearch();
```

#### Execution

##### `get(): ElasticsearchResponse`

Executes the search.

```php
$response = $search->get();
```

##### `promise(): Promise`

Returns a promise for async execution.

```php
$promise = $search->promise();
```

## Query Builder

### Class: `Sigmie\Query\NewQuery`

Low-level query builder for precise control.

#### Required Methods

##### `properties(NewProperties $properties): self`

Sets query properties.

```php
$query->properties($properties);
```

#### Simple Queries

##### `matchAll(): self`

Matches all documents.

```php
$query->matchAll();
```

##### `matchNone(): self`

Matches no documents.

```php
$query->matchNone();
```

##### `term(string $field, $value): self`

Exact term match.

```php
$query->term('category', 'sci-fi');
$query->term('year', 1999);
```

##### `match(string $field, string $value): self`

Full-text match.

```php
$query->match('title', 'matrix');
```

##### `multiMatch(array $fields, string $value): self`

Multi-field match.

```php
$query->multiMatch(['title', 'description'], 'matrix');
```

##### `range(string $field, array $conditions): self`

Range query.

```php
$query->range('year', ['>' => 1990, '<=' => 2000]);
$query->range('rating', ['>=' => 8.0]);
```

##### `exists(string $field): self`

Field existence check.

```php
$query->exists('director');
```

##### `ids(array $ids): self`

Match by document IDs.

```php
$query->ids(['doc1', 'doc2', 'doc3']);
```

##### `terms(string $field, array $values): self`

Multiple term match.

```php
$query->terms('category', ['action', 'sci-fi', 'thriller']);
```

##### String Queries

```php
$query->regex(string $field, string $pattern);      // Regular expression
$query->wildcard(string $field, string $pattern);   // Wildcard pattern  
$query->prefix(string $field, string $prefix);      // Prefix match
$query->fuzzy(string $field, string $value);        // Fuzzy match
```

#### Boolean Queries

##### `bool(callable $callback): self`

Complex boolean query.

```php
$query->bool(function (Boolean $bool) {
    $bool->must()->match('title', 'matrix');
    $bool->filter()->range('year', ['>' => 1990]);
    $bool->should()->term('genre', 'sci-fi');
    $bool->mustNot()->term('rating', 'R');
});
```

**Boolean Context Methods:**
- `must()` - Must match (affects score)
- `filter()` - Must match (no scoring)
- `should()` - Should match (OR logic)
- `mustNot()` - Must not match

#### Advanced Queries

##### `parse(string $queryString): self`

Parse query string.

```php
$query->parse('title:"The Matrix" AND year>1990');
```

##### `scriptScore(string $source, string $boostMode = 'multiply'): self`

Custom script scoring.

```php
$query->scriptScore(
    source: "Math.log(2 + doc['popularity'].value)",
    boostMode: 'replace'
);
```

##### `functionScore(): self`

Function score query.

```php
$query->functionScore();
```

#### Aggregations

##### `facets(string $facets): self`

Add aggregations.

```php
$query->facets('category');
```

#### Sorting and Pagination

##### `sortString(string $sort): self`

Set sort order.

```php
$query->sortString('year:desc _score:desc');
```

##### `from(int $from): self`

Set offset.

```php
$query->from(10);
```

##### `size(int $size): self`

Set limit.

```php
$query->size(20);
```

#### Execution

##### `get(): ElasticsearchResponse`

Execute query.

```php
$response = $query->get();
```

##### `getDSL(): array`

Get query DSL for debugging.

```php
$dsl = $query->getDSL();
```

## Collections

### Class: `Sigmie\Collection\Collection`

Interface for document collections.

#### Methods

##### `add(Document $document): self`

Adds a single document.

```php
$collection->add($document);
```

##### `merge(array $documents): self`

Adds multiple documents.

```php
$collection->merge([$doc1, $doc2, $doc3]);
```

##### `count(): int`

Counts documents.

```php
$total = $collection->count();
```

##### `random(int $count = 10): Collection`

Gets random documents.

```php
$randomDocs = $collection->random(5);
```

##### `each(callable $callback): void`

Iterates through documents.

```php
$collection->each(function (Document $doc) {
    echo $doc['title'] . "\n";
});
```

##### `toArray(): array`

Converts to array.

```php
$documents = $collection->toArray();
```

### Class: `Sigmie\Collection\AliveCollection`

Real-time collection with immediate availability.

Inherits all Collection methods with immediate refresh.

## Response Objects

### Class: `Sigmie\Http\ElasticsearchResponse`

Represents Elasticsearch response.

#### Methods

##### `json(string $path = null): mixed`

Gets JSON response data.

```php
$fullResponse = $response->json();
$hits = $response->json('hits.hits');
$total = $response->json('hits.total.value');
```

##### `hits(): array`

Gets search hits.

```php
$hits = $response->hits();
```

##### `total(): int`

Gets total hit count.

```php
$totalHits = $response->total();
```

##### `isOk(): bool`

Checks if response is successful.

```php
if ($response->isOk()) {
    // Handle success
}
```

##### `getStatusCode(): int`

Gets HTTP status code.

```php
$statusCode = $response->getStatusCode();
```

## Error Handling

### Common Exceptions

```php
// Connection errors
try {
    $sigmie = Sigmie::create(['invalid-host']);
} catch (ConnectionException $e) {
    // Handle connection failure
}

// Index errors  
try {
    $index = $sigmie->index('nonexistent');
} catch (IndexNotFoundException $e) {
    // Handle missing index
}

// Query errors
try {
    $response = $sigmie->newQuery('movies')
        ->parse('invalid syntax')
        ->get();
} catch (QueryException $e) {
    // Handle query errors
}
```

## Usage Examples

### Complete CRUD Example

```php
use Sigmie\Sigmie;
use Sigmie\Mappings\NewProperties;
use Sigmie\Document\Document;

// Initialize client
$sigmie = Sigmie::create(['127.0.0.1:9200']);

// Define properties
$properties = new NewProperties;
$properties->title('title');
$properties->name('director');
$properties->number('year')->integer();
$properties->number('rating')->float();
$properties->category('genre');

// Create index
$sigmie->newIndex('movies')
    ->properties($properties)
    ->lowercase()
    ->create();

// Create documents
$movies = $sigmie->collect('movies', refresh: true);
$movies->merge([
    new Document([
        'title' => 'The Matrix',
        'director' => 'The Wachowskis',
        'year' => 1999,
        'rating' => 8.7,
        'genre' => 'Sci-Fi'
    ], 'matrix_1999'),
    new Document([
        'title' => 'Inception',
        'director' => 'Christopher Nolan', 
        'year' => 2010,
        'rating' => 8.8,
        'genre' => 'Sci-Fi'
    ])
]);

// Search
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matrix sci-fi')
    ->typoTolerance()
    ->highlighting(['title'])
    ->weight(['title' => 3, 'director' => 1])
    ->filters('year>1990 AND rating>=8.0')
    ->sort('rating:desc')
    ->get();

// Process results
$hits = $response->hits();
foreach ($hits as $hit) {
    echo $hit['_source']['title'] . " (" . $hit['_source']['year'] . ")\n";
    if (isset($hit['highlight']['title'])) {
        echo "Highlighted: " . $hit['highlight']['title'][0] . "\n";
    }
}
```

This API reference covers the main classes and methods in Sigmie. For detailed examples and use cases, see the specific documentation sections for each feature.