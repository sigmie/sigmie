---
title: Core Concepts
short_description: Understand the fundamental concepts of Sigmie and Elasticsearch
keywords: [core concepts, fundamentals, client, indices, documents, properties]
category: Core Concepts
order: 1
related_pages: [index, document, mappings, search]
---

# Core Concepts

Understanding these core concepts is essential for working effectively with Sigmie. This guide explains the fundamental building blocks and how they work together.

## Overview

Sigmie operates on four main concepts:

1. **Client** - Your connection to Elasticsearch
2. **Indices** - Containers for related documents
3. **Documents** - Individual JSON records
4. **Properties** - Schema definitions for documents

## The Sigmie Client

The Sigmie client is your main interface to Elasticsearch. It manages connections and provides methods for all operations.

### Creating a Client

```php
use Sigmie\Sigmie;

$sigmie = Sigmie::create(
    hosts: ['127.0.0.1:9200'],
    config: ['connect_timeout' => 15]
);
```

### Client Capabilities

The client provides access to:
- Index management operations
- Document collections
- Search functionality  
- Query building
- Connection management

```php
// Index operations
$index = $sigmie->newIndex('movies');
$existingIndex = $sigmie->index('movies');

// Document collections
$collection = $sigmie->collect('movies');

// Search operations
$search = $sigmie->newSearch('movies');
$query = $sigmie->newQuery('movies');
```

## Indices

An Index is a logical container for related documents, similar to a database table. It defines how documents are stored, analyzed, and searched.

### What is an Index?

Think of an Index as:
- A drawer containing related items (documents)
- A database table with flexible schema
- A search-optimized storage container

```bash
Index: movies
├── Document: The Matrix (1999)
├── Document: Inception (2010)
├── Document: Pulp Fiction (1994)
└── ...
```

### Index Lifecycle

#### 1. Creation

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->title('title');
$properties->name('director');
$properties->number('year')->integer();

$index = $sigmie->newIndex('movies')
    ->properties($properties)
    ->create();
```

#### 2. Configuration

```php
$index = $sigmie->newIndex('movies')
    ->properties($properties)
    ->shards(3)
    ->replicas(1)
    ->lowercase()
    ->tokenizeOnWhitespaces()
    ->create();
```

#### 3. Management

```php
// Get existing index
$index = $sigmie->index('movies');

// Update index
$sigmie->index('movies')->update(function($updateIndex) {
    $updateIndex->properties($newProperties);
});

// Delete index
$sigmie->index('movies')->delete();
```

### Index Settings

Key index configurations:

**Shards**: Distribute data across cluster nodes
```php
->shards(3)  // Split index into 3 primary shards
```

**Replicas**: Create copies for redundancy
```php
->replicas(2)  // Create 2 replica copies of each shard
```

**Analysis**: Define text processing
```php
->lowercase()           // Convert text to lowercase
->tokenizeOnWhitespaces()  // Split on whitespace
->trim()               // Remove leading/trailing spaces
```

## Documents

Documents are individual JSON records stored within an Index. They represent the actual data you want to search.

### Document Structure

```php
use Sigmie\Document\Document;

$document = new Document([
    'title' => 'The Matrix',
    'director' => 'The Wachowskis', 
    'year' => 1999,
    'genre' => 'Sci-Fi',
    'rating' => 8.7,
    'cast' => ['Keanu Reeves', 'Laurence Fishburne'],
    'metadata' => [
        'budget' => 63000000,
        'box_office' => 467222824
    ]
]);
```

### Document IDs

Documents can have explicit IDs:

```php
// Auto-generated ID
$document = new Document(['title' => 'Inception']);

// Custom ID
$document = new Document([
    'title' => 'The Matrix'
], 'matrix_1999');
```

### Document Types

Documents support various data types:

```php
$document = new Document([
    // Text fields
    'title' => 'Movie Title',
    'description' => 'Long description...',
    
    // Numbers
    'year' => 1999,           // Integer
    'rating' => 8.7,          // Float
    
    // Booleans
    'is_available' => true,
    
    // Dates
    'release_date' => '1999-03-31T00:00:00Z',
    
    // Arrays
    'genres' => ['Action', 'Sci-Fi'],
    'cast' => ['Actor 1', 'Actor 2'],
    
    // Objects
    'director' => [
        'name' => 'Christopher Nolan',
        'birth_year' => 1970
    ],
    
    // Geographic points
    'filming_location' => [
        'lat' => 34.0522,
        'lon' => -118.2437
    ]
]);
```

## Properties

Properties define the schema and behavior of document fields. They determine how data is stored, analyzed, and searchable.

### Why Properties Matter

Properties control:
- **Data types** - How values are interpreted
- **Analysis** - How text is processed for search
- **Search behavior** - Which queries work on each field
- **Storage** - How data is stored and indexed

### Basic Properties

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;

// Text fields
$properties->text('description');    // Full-text search
$properties->keyword('isbn');        // Exact matches

// Numbers  
$properties->number('year')->integer();
$properties->number('rating')->float();

// Other types
$properties->bool('is_available');
$properties->date('release_date');
$properties->geoPoint('location');
```

### High-Level Properties

Sigmie provides semantic property types:

```php
$properties = new NewProperties;

// Optimized for specific use cases
$properties->title('movie_title');        // Movie/book titles
$properties->name('director_name');       // Person/place names  
$properties->category('genre');           // Categories/classifications
$properties->tags('tags');                // Tag collections
$properties->price('ticket_price');       // Monetary values
$properties->email('contact_email');      // Email addresses
$properties->longText('synopsis');        // Long descriptions
```

### Complex Properties

```php
// Nested objects
$properties->nested('cast', function (NewProperties $props) {
    $props->name('actor_name');
    $props->keyword('character');
    $props->number('screen_time')->integer();
});

// Objects (single, not arrays)
$properties->object('director', function (NewProperties $props) {
    $props->name('name');
    $props->number('birth_year')->integer();
    $props->email('contact');
});
```

### Semantic Search Properties

```php
// Enable vector search
$properties->text('description')->semantic();

// Advanced semantic configuration
$properties->text('plot')
    ->semantic(accuracy: 5, dimensions: 768);
```

## Collections

Collections represent a connected Index that you can add documents to and manipulate.

### Creating Collections

```php
// Basic collection
$movies = $sigmie->collect('movies');

// Real-time collection (for testing)
$movies = $sigmie->collect('movies', refresh: true);
```

### Collection Operations

```php
// Add single document
$movies->add($document);

// Add multiple documents (preferred)
$movies->merge([$doc1, $doc2, $doc3]);

// Count documents
$count = $movies->count();

// Get random documents
$randomMovies = $movies->random(5);

// Iterate through documents
$movies->each(function($document) {
    echo $document['title'] . "\n";
});
```

### Collection Types

**Standard Collection**: Normal indexing timing
```php
$movies = $sigmie->collect('movies');
$movies->merge($documents);
// Documents available for search after ~1 second
```

**Alive Collection**: Immediate availability (testing only)
```php
$movies = $sigmie->collect('movies', refresh: true);
$movies->merge($documents);
// Documents immediately available for search
```

## Search vs Query

Sigmie provides two approaches for retrieving data:

### Search Builder - High-Level API

Best for user-facing search with built-in features:

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matrix sci-fi')
    ->typoTolerance()
    ->highlighting(['title', 'description'])
    ->filters('year>1990')
    ->get();
```

Features include:
- Typo tolerance
- Highlighting
- Faceted search
- Weight adjustment
- Semantic search

### Query Builder - Low-Level API

Best for complex boolean logic and precise control:

```php
$response = $sigmie->newQuery('movies')
    ->properties($properties)
    ->bool(function ($bool) {
        $bool->must()->match('title', 'matrix');
        $bool->filter()->range('year', ['>' => 1990]);
        $bool->should()->term('genre', 'sci-fi');
    })
    ->get();
```

Features include:
- Full boolean query control
- All Elasticsearch query types
- Custom scoring
- Advanced aggregations

## Analysis Process

Understanding how Elasticsearch processes text is crucial for effective searching.

### What is Analysis?

Analysis transforms text for efficient searching:

1. **Input**: "The Matrix Reloaded"
2. **Tokenization**: ["The", "Matrix", "Reloaded"]  
3. **Filtering**: ["matrix", "reloaded"] (lowercase, remove stopwords)
4. **Storage**: Optimized tokens ready for searching

### Index-Time Analysis

```php
$sigmie->newIndex('movies')
    ->tokenizeOnWhitespaces()  // Split on spaces
    ->lowercase()              // Convert to lowercase
    ->trim()                   // Remove whitespace
    ->create();
```

### Search-Time Analysis

Query strings undergo the same analysis:
- Query: "Matrix Reloaded" → ["matrix", "reloaded"]
- Matches documents containing these terms

### Testing Analysis

```php
$index = $sigmie->index('movies');
$tokens = $index->analyze('The Matrix Reloaded');
// Returns: ["matrix", "reloaded"]
```

## Data Flow

Understanding the complete data flow helps debug and optimize:

```
1. Create Index with Properties
   ↓
2. Configure Analysis Settings  
   ↓
3. Create Collection
   ↓
4. Add Documents (analyzed and indexed)
   ↓
5. Search/Query (query string analyzed)
   ↓
6. Match analyzed query against analyzed documents
   ↓
7. Return scored results
```

## Relationships Between Concepts

### Client → Index → Collection → Documents

```php
// Client creates/accesses indices
$index = $sigmie->newIndex('movies')->create();

// Client creates collections from indices  
$movies = $sigmie->collect('movies');

// Collections contain documents
$movies->merge([$document1, $document2]);

// Searches operate on indices
$results = $sigmie->newSearch('movies')
    ->queryString('matrix')
    ->get();
```

### Properties → Analysis → Search

```php
// Properties define field behavior
$properties = new NewProperties;
$properties->title('title');  // Optimized for titles

// Analysis processes text fields
$sigmie->newIndex('movies')
    ->properties($properties)
    ->lowercase()  // Analysis setting
    ->create();

// Search leverages both
$results = $sigmie->newSearch('movies')
    ->properties($properties)  // Use same properties
    ->queryString('matrix')    // Will be analyzed
    ->get();
```

## Best Practices

### 1. Consistent Property Usage

Always use the same properties for index creation and searching:

```php
// Define once
$properties = new NewProperties;
$properties->title('title');
$properties->name('director');

// Use everywhere
$sigmie->newIndex('movies')->properties($properties)->create();
$sigmie->newSearch('movies')->properties($properties)->get();
$sigmie->collect('movies')->properties($properties);
```

### 2. Appropriate Field Types

Choose the right property type for your data:

```php
// Good
$properties->title('movie_title');      // For titles
$properties->name('director_name');     // For names
$properties->price('ticket_price');     // For prices
$properties->longText('description');   // For long content

// Avoid
$properties->text('price');             // Price as text
$properties->keyword('description');    // Long text as keyword
```

### 3. Index Design

Design indices for your access patterns:

```php
// Single-purpose indices
$sigmie->newIndex('movies');     // Just movies
$sigmie->newIndex('actors');     // Just actors

// Avoid
$sigmie->newIndex('entertainment');  // Mixed content types
```

### 4. Collection Usage

Use appropriate collection types:

```php
// Production
$movies = $sigmie->collect('movies');        // Normal timing

// Testing only
$movies = $sigmie->collect('movies', refresh: true);
```

## Common Patterns

### CRUD Operations

```php
// Create
$movies = $sigmie->collect('movies');
$movies->add(new Document(['title' => 'New Movie']));

// Read  
$results = $sigmie->newSearch('movies')
    ->queryString('new movie')
    ->get();

// Update (re-index with same ID)
$movies->add(new Document(['title' => 'Updated Movie'], 'movie_id'));

// Delete (reindex without unwanted documents)
// Or use Elasticsearch delete APIs directly
```

### Search Patterns

```php
// User search
$sigmie->newSearch('movies')
    ->queryString($userInput)
    ->typoTolerance()
    ->highlighting(['title'])
    ->get();

// Filtered search  
$sigmie->newSearch('products')
    ->queryString($userInput)
    ->filters('in_stock:true AND price<100')
    ->facets('category')
    ->get();

// Semantic search
$sigmie->newSearch('articles')
    ->semantic()
    ->queryString('artificial intelligence')
    ->get();
```

## Next Steps

With these core concepts understood, you're ready to:

1. **[Create indices](index.md)** with proper mappings and analysis
2. **[Manage documents](document.md)** efficiently 
3. **[Build searches](search.md)** with advanced features
4. **[Construct queries](query.md)** with precise control
5. **[Integrate with Laravel](laravel-scout.md)** for web applications

These concepts form the foundation of all Sigmie operations. Master them to build powerful search experiences.