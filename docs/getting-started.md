---
title: Getting Started
short_description: Comprehensive guide to getting started with Sigmie and Elasticsearch
keywords: [getting started, guide, tutorial, elasticsearch, search, php]
category: Getting Started
order: 4
related_pages: [introduction, installation, quick-start, core-concepts]
---

# Getting Started with Sigmie

Sigmie is a powerful PHP library designed to simplify working with Elasticsearch for search functionality. This guide will walk you through everything you need to know to get started with Sigmie.

## What is Sigmie?

Sigmie is a PHP library that provides an intuitive, high-level API for Elasticsearch operations. Instead of writing complex Elasticsearch queries, you can use Sigmie's fluent interface to:

- Create and manage indices
- Index documents with type validation
- Perform powerful searches with built-in features like typo tolerance and highlighting
- Work with semantic search using vector embeddings
- Integrate seamlessly with Laravel Scout

## System Requirements

Before you begin, ensure your system meets these requirements:

- **PHP >= 8.1**
- **Elasticsearch ^7** or **^8**
- **Composer** for package management

## Installation

Install Sigmie via Composer:

```bash
composer require sigmie/sigmie
```

Sigmie is compatible with PSR-4 autoloading:

```php
require_once 'vendor/autoload.php';
```

## Initial Setup

### Basic Configuration

Create a Sigmie client instance to connect to your Elasticsearch cluster:

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: ['connect_timeout' => 15]
);
```

The `hosts` parameter specifies your Elasticsearch location, and `config` accepts [Guzzle HTTP client options](https://docs.guzzlephp.org/en/stable/request-options.html).

### Advanced Configuration

For more control, you can manually configure the connection:

```php
use Sigmie\Http\JSONClient;
use Sigmie\Base\Http\ElasticsearchConnection;
use Sigmie\Sigmie;

// Create HTTP client with multiple hosts and custom config
$jsonClient = JSONClient::create(
    hosts: ['10.0.0.1:9200', '10.0.0.2:9200', '10.0.0.3:9200'],
    config: [
        'allow_redirects' => false,
        'http_errors' => false,
        'connect_timeout' => 15,
    ]
);

// Establish Elasticsearch connection
$elasticsearchConnection = new ElasticsearchConnection($jsonClient);

// Initialize Sigmie client
$sigmie = new Sigmie($elasticsearchConnection);
```

## Core Concepts

### 1. Indices

An Index in Elasticsearch is like a database table - it's a container for related documents. Think of it as:
- A drawer containing toys (documents)
- A database table
- A collection of similar items

### 2. Documents

A Document is a JSON object stored in an Index:

```php
use Sigmie\Document\Document;

$document = new Document([
    'title' => 'The Matrix',
    'director' => 'The Wachowskis',
    'year' => 1999,
    'rating' => 8.7
]);
```

### 3. Properties

Properties define the structure and behavior of your documents:

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->title('title');        // Movie title
$properties->name('director');      // Director name
$properties->number('year')->integer();  // Release year
$properties->number('rating')->float();  // IMDb rating
```

## Your First Index

Let's create a complete example using a movies database:

### Step 1: Define Properties

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->title('title');
$properties->name('director');
$properties->category('genre');
$properties->number('year')->integer();
$properties->number('rating')->float();
$properties->longText('description');
$properties->date('release_date');
$properties->bool('is_available');
```

### Step 2: Create the Index

```php
$index = $sigmie->newIndex('movies')
    ->properties($properties)
    ->create();
```

### Step 3: Add Documents

```php
use Sigmie\Document\Document;

$documents = [
    new Document([
        'title' => 'The Matrix',
        'director' => 'The Wachowskis',
        'genre' => 'Sci-Fi',
        'year' => 1999,
        'rating' => 8.7,
        'description' => 'A computer programmer discovers reality as he knows it is a simulation.',
        'release_date' => '1999-03-31',
        'is_available' => true
    ]),
    new Document([
        'title' => 'Inception',
        'director' => 'Christopher Nolan',
        'genre' => 'Sci-Fi',
        'year' => 2010,
        'rating' => 8.8,
        'description' => 'A thief who steals corporate secrets through dream-sharing technology.',
        'release_date' => '2010-07-16',
        'is_available' => true
    ]),
    new Document([
        'title' => 'Pulp Fiction',
        'director' => 'Quentin Tarantino',
        'genre' => 'Crime',
        'year' => 1994,
        'rating' => 8.9,
        'description' => 'The lives of two mob hitmen, a boxer, and other criminals intertwine.',
        'release_date' => '1994-10-14',
        'is_available' => false
    ])
];

$movies = $sigmie->collect('movies', refresh: true);
$movies->merge($documents);
```

### Step 4: Search Your Data

Now you can search through your movies:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matrix sci-fi')
    ->get();

$hits = $response->json('hits.hits');
```

## Advanced Features

### Typo Tolerance

Handle spelling mistakes automatically:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matirx')  // Typo in "matrix"
    ->typoTolerance(oneTypoChars: 3, twoTypoChars: 6)
    ->typoTolerantAttributes(['title', 'director'])
    ->get();
```

### Highlighting

Highlight matching terms in results:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matrix')
    ->highlighting(
        ['title', 'description'],
        prefix: '<mark>',
        suffix: '</mark>'
    )
    ->get();
```

### Filtering

Filter results based on specific criteria:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('action')
    ->filters('year>2000 AND rating>=8.0 AND is_available:true')
    ->get();
```

### Sorting

Sort results by multiple fields:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('sci-fi')
    ->sort('rating:desc year:desc')
    ->get();
```

### Pagination

Implement pagination for large result sets:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('action')
    ->from(20)      // Skip first 20 results
    ->size(10)      // Return 10 results
    ->get();
```

## Working with Collections

### Counting Documents

```php
$movies = $sigmie->collect('movies', refresh: true);
$totalMovies = $movies->count();
```

### Getting Random Documents

```php
$movies = $sigmie->collect('movies');
$randomMovies = $movies->random(5);  // Get 5 random movies
```

### Iterating Through Documents

```php
$movies = $sigmie->collect('movies');

// Memory-efficient iteration
$movies->each(function (Document $movie) {
    echo $movie['title'] . " (" . $movie['year'] . ")\n";
});
```

## Index Management

### Getting Index Information

```php
$index = $sigmie->index('movies');

// Get index mappings
$mappings = $index->mappings;

// Get properties
$properties = $index->mappings->properties();

// Get raw Elasticsearch mapping
$rawMapping = $index->raw;
```

### Updating Index Settings

```php
use Sigmie\Index\UpdateIndex;

$sigmie->index('movies')->update(function(UpdateIndex $updateIndex) {
    $updateIndex->properties($newProperties);
    $updateIndex->lowercase();
    $updateIndex->tokenizeOnWhitespaces();
});
```

### Deleting an Index

```php
$sigmie->index('movies')->delete();
```

## Production Considerations

### Performance Tips

1. **Use batch operations** for multiple documents:
```php
// Good: Batch operation
$movies->merge($manyDocuments);

// Avoid: Individual operations
foreach ($manyDocuments as $doc) {
    $movies->add($doc);  // Inefficient
}
```

2. **Avoid refresh in production**:
```php
// Production: Let Elasticsearch handle timing
$movies = $sigmie->collect('movies');

// Development/Testing only
$movies = $sigmie->collect('movies', refresh: true);
```

3. **Use appropriate field types** for your data
4. **Configure proper shards and replicas** for your cluster
5. **Use filters instead of queries** when you don't need scoring

### Error Handling

Always wrap operations in try-catch blocks:

```php
try {
    $response = $sigmie->newSearch('movies')
        ->properties($properties)
        ->queryString('matrix')
        ->get();
        
    $hits = $response->json('hits.hits');
} catch (Exception $e) {
    echo "Search failed: " . $e->getMessage();
}
```

## Next Steps

Now that you understand the basics, explore these advanced topics:

1. **[Index Management](index.md)** - Learn about analysis, mappings, and index optimization
2. **[Document Management](document.md)** - Master document operations and validation
3. **[Search & Querying](search.md)** - Explore advanced search features
4. **[Laravel Scout Integration](laravel-scout.md)** - Integrate with Laravel applications
5. **[Semantic Search](semantic-search.md)** - Implement vector-based searching
6. **[Facets & Aggregations](facets.md)** - Build faceted search interfaces

## Common Patterns

### E-commerce Search

```php
$properties = new NewProperties;
$properties->name('name');
$properties->longText('description');
$properties->price('price');
$properties->category('category');
$properties->tags('tags');
$properties->bool('in_stock');

$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString('wireless headphones')
    ->filters('in_stock:true AND price<200')
    ->facets('category')
    ->sort('_score:desc price:asc')
    ->get();
```

### Content Management

```php
$properties = new NewProperties;
$properties->title('title');
$properties->longText('content');
$properties->name('author');
$properties->category('category');
$properties->tags('tags');
$properties->date('published_at');
$properties->bool('is_published');

$response = $sigmie->newSearch('articles')
    ->properties($properties)
    ->queryString('elasticsearch tutorial')
    ->filters('is_published:true')
    ->fields(['title', 'content'])
    ->highlighting(['title', 'content'])
    ->get();
```

## Getting Help

- **Documentation**: Explore the complete documentation for detailed information on all features
- **GitHub Issues**: Report bugs and request features
- **Community**: Join discussions with other Sigmie users

Sigmie makes Elasticsearch accessible and powerful. With these fundamentals, you're ready to build sophisticated search experiences for your applications.