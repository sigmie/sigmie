# Document Management

Documents are JSON objects stored within an Index. In Sigmie, you work with documents through the `Document` class and manage them using collection methods.

## Introduction

Sigmie treats an Index as a **Collection** that contains instances of `Document\Document`. This provides a fluent and intuitive API for managing your documents.

## Creating Documents

### Basic Document Creation

```php
use Sigmie\Document\Document;

// Simple document
$document = new Document(['name' => 'Snow White']);

// Document with multiple fields
$document = new Document([
    'title' => 'The Lion King',
    'genre' => 'Animation',
    'year' => 1994,
    'rating' => 8.5,
    'tags' => ['family', 'musical', 'coming-of-age']
]);
```

### Document with Custom ID

```php
// Document with specific ID
$document = new Document([
    'title' => 'Frozen',
    'genre' => 'Animation'
], 'movie_123');  // Custom document ID
```

### Complex Document Structures

```php
// Nested document structure
$document = new Document([
    'title' => 'Inception',
    'director' => [
        'name' => 'Christopher Nolan',
        'birth_year' => 1970
    ],
    'cast' => [
        ['name' => 'Leonardo DiCaprio', 'role' => 'Dom Cobb'],
        ['name' => 'Marion Cotillard', 'role' => 'Mal']
    ],
    'metadata' => [
        'runtime' => 148,
        'budget' => 160000000,
        'box_office' => 836800000
    ]
]);
```

## Collecting an Index

To work with documents in an Index, you first need to "collect" it:

```php
// Basic collection
$movies = $sigmie->collect('movies');

// Collection with refresh for immediate availability
$movies = $sigmie->collect('movies', refresh: true);
```

The `refresh: true` parameter makes documents immediately searchable, which is useful for testing but should be avoided in production.

@danger
Using `refresh: true` is **NOT** recommended in production code as it impacts performance.
@enddanger

## Adding Documents

### Adding Single Documents

```php
$document = new Document(['name' => 'Mickey Mouse']);
$movies = $sigmie->collect('movies');

$movies->add($document);
```

### Adding Multiple Documents

```php
$documents = [
    new Document(['name' => 'Snow White']),
    new Document(['name' => 'Cinderella']),
    new Document(['name' => 'Sleeping Beauty'])
];

$movies = $sigmie->collect('movies', refresh: true);
$movies->merge($documents);
```

### Bulk Operations

For better performance with large datasets:

```php
$documents = [];
for ($i = 0; $i < 1000; $i++) {
    $documents[] = new Document([
        'title' => "Movie {$i}",
        'year' => rand(1950, 2024),
        'rating' => rand(1, 10)
    ]);
}

$movies = $sigmie->collect('movies');
$movies->merge($documents);  // Bulk insert
```

## Document Validation with Properties

When using properties, documents are automatically validated:

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->name('title');
$properties->date('release_date');
$properties->number('rating')->float();

// Valid document
$validDoc = new Document([
    'title' => 'The Matrix',
    'release_date' => '1999-03-31T00:00:00Z',
    'rating' => 8.7
]);

// Invalid document (will be caught during indexing)
$invalidDoc = new Document([
    'title' => 'Invalid Movie',
    'release_date' => 'not-a-date',  // Invalid date format
    'rating' => 'not-a-number'       // Invalid rating
]);

$movies = $sigmie->collect('movies')
    ->properties($properties)
    ->merge([$validDoc, $invalidDoc]);  // Validation occurs here
```

## Indexing Timing

### Async Indexing (Default)

By default, Elasticsearch operates in "near real-time" mode:

```php
$sigmie->newIndex('movies')->create();

$doc = new Document(['name' => 'Snow White']);
$movies = $sigmie->collect('movies');
$movies->add($doc);

$movies->count(); // 0 - document not immediately available
```

Documents are usually available for searching after about 1 second.

### Sync Indexing (Testing)

For testing or when you need immediate availability:

```php
$doc = new Document(['name' => 'Snow White']);
$movies = $sigmie->collect('movies', refresh: true);
$movies->add($doc);

$movies->count(); // 1 - document immediately available
```

## Working with Collections

### Counting Documents

```php
$movies = $sigmie->collect('movies', refresh: true);
$totalMovies = $movies->count();
```

### Checking Collection State

```php
$movies = $sigmie->collect('movies');

// Check if collection is "alive" (has real-time data)
if ($movies instanceof AliveCollection) {
    // Real-time collection with refresh enabled
    $count = $movies->count();
}
```

### Iterating Through Documents

```php
$movies = $sigmie->collect('movies', refresh: true);

// Add some documents first
$movies->merge([
    new Document(['title' => 'Movie 1']),
    new Document(['title' => 'Movie 2']),
    new Document(['title' => 'Movie 3'])
]);

// Lazy iteration (memory efficient for large collections)
$movies->each(function (Document $document) {
    echo $document['title'] . "\n";
});
```

### Converting to Array

```php
$movies = $sigmie->collect('movies', refresh: true);
$movies->merge([/* documents */]);

// Get all documents as array
$documentsArray = $movies->toArray();
```

### Getting Random Documents

You can retrieve random documents from a collection using the `random()` method:

```php
$movies = $sigmie->collect('movies');

// Get 10 random documents (returns a collection)
$randomMovies = $movies->random(10);

// Get a single random document
$randomMovie = $movies->random(1);

// Convert random documents to array
$randomArray = $movies->random(5)->toArray();
```

This is useful for:
- Displaying sample data in your UI
- Testing and development
- Creating recommendation systems
- Generating preview content

## Document Operations

### Updating Documents

To update documents, you typically re-index them with the same ID:

```php
// Original document
$original = new Document([
    'title' => 'The Matrix',
    'year' => 1999
], 'matrix_1');

$movies = $sigmie->collect('movies', refresh: true);
$movies->add($original);

// Updated document (same ID)
$updated = new Document([
    'title' => 'The Matrix',
    'year' => 1999,
    'rating' => 8.7,  // New field
    'updated_at' => date('c')
], 'matrix_1');

$movies->add($updated);  // This will update the existing document
```

### Deleting Documents

Currently, document deletion is handled through Elasticsearch's native APIs or by reindexing without the unwanted documents.

## Working with Complex Data Types

### Date Fields

```php
$properties = new NewProperties;
$properties->date('created_at');

$document = new Document([
    'title' => 'New Movie',
    'created_at' => '2023-04-07T12:38:29.000000Z'  // ISO format
]);
```

### Geo Points

```php
$properties = new NewProperties;
$properties->geoPoint('location');

$document = new Document([
    'venue' => 'Cinema Downtown',
    'location' => [
        'lat' => 40.7128,
        'lon' => -74.0060
    ]
]);
```

### Nested Objects

```php
$properties = new NewProperties;
$properties->nested('cast', function (NewProperties $props) {
    $props->name('actor');
    $props->keyword('role');
});

$document = new Document([
    'title' => 'Avengers',
    'cast' => [
        ['actor' => 'Robert Downey Jr.', 'role' => 'Iron Man'],
        ['actor' => 'Chris Evans', 'role' => 'Captain America']
    ]
]);
```

## Performance Considerations

### Batch Operations

Always prefer batch operations for multiple documents:

```php
// Good: Batch operation
$movies->merge($manyDocuments);

// Avoid: Individual operations
foreach ($manyDocuments as $doc) {
    $movies->add($doc);  // Inefficient for large datasets
}
```

### Memory Management

For large collections, use lazy iteration:

```php
// Memory efficient for large datasets
$movies->each(function (Document $doc) {
    // Process each document
    processDocument($doc);
});

// Memory intensive for large datasets
$allDocs = $movies->toArray();  // Loads everything into memory
```

### Index Optimization

Consider refresh strategies based on your use case:

```php
// Production: Let Elasticsearch handle refresh timing
$movies = $sigmie->collect('movies');

// Development/Testing: Force immediate refresh
$movies = $sigmie->collect('movies', refresh: true);

// Batch processing: Disable refresh during bulk operations
$movies = $sigmie->collect('movies', refresh: false);
// ... add many documents ...
// Manually refresh when done
$sigmie->index('movies')->refresh();
```

## Common Patterns

### E-commerce Products

```php
$properties = new NewProperties;
$properties->name('name');
$properties->longText('description');
$properties->price('price');
$properties->category('category');
$properties->tags('tags');
$properties->bool('in_stock');
$properties->date('created_at');

$product = new Document([
    'name' => 'Wireless Headphones',
    'description' => 'High-quality wireless headphones with noise cancellation',
    'price' => 199.99,
    'category' => 'Electronics',
    'tags' => ['audio', 'wireless', 'noise-cancelling'],
    'in_stock' => true,
    'created_at' => date('c')
]);

$products = $sigmie->collect('products')
    ->properties($properties)
    ->merge([$product]);
```

### User Profiles

```php
$properties = new NewProperties;
$properties->name('username');
$properties->email('email');
$properties->number('age')->integer();
$properties->tags('interests');
$properties->nested('address', function (NewProperties $props) {
    $props->keyword('street');
    $props->keyword('city');
    $props->keyword('country');
});

$user = new Document([
    'username' => 'john_doe',
    'email' => 'john@example.com',
    'age' => 30,
    'interests' => ['technology', 'sports', 'travel'],
    'address' => [
        'street' => '123 Main St',
        'city' => 'New York',
        'country' => 'USA'
    ]
]);
```

### Content Management

```php
$properties = new NewProperties;
$properties->title('title');
$properties->longText('content');
$properties->name('author');
$properties->tags('tags');
$properties->category('category');
$properties->date('published_at');
$properties->bool('is_published');

$article = new Document([
    'title' => 'Getting Started with Elasticsearch',
    'content' => 'Elasticsearch is a powerful search engine...',
    'author' => 'Jane Smith',
    'tags' => ['elasticsearch', 'search', 'tutorial'],
    'category' => 'Technology',
    'published_at' => '2024-01-15T10:00:00Z',
    'is_published' => true
]);
```

## Error Handling

```php
try {
    $movies = $sigmie->collect('movies', refresh: true);
    $movies->merge($documents);
    
    echo "Indexed " . count($documents) . " documents successfully";
} catch (Exception $e) {
    echo "Error indexing documents: " . $e->getMessage();
}
```

## Best Practices

1. **Use Batch Operations**: Always prefer `merge()` over individual `add()` calls for multiple documents
2. **Validate Data**: Use properties to validate document structure
3. **Handle Dates Properly**: Use ISO 8601 format for date fields
4. **Memory Management**: Use lazy iteration for large datasets
5. **Error Handling**: Always wrap operations in try-catch blocks
6. **Production Refresh**: Avoid `refresh: true` in production environments
7. **Custom IDs**: Use meaningful document IDs when you need to update specific documents

```php
// Good pattern
$properties = new NewProperties;
$properties->name('title');
$properties->date('created_at');

try {
    $movies = $sigmie->collect('movies')
        ->properties($properties)
        ->merge($validatedDocuments);
    
    echo "Successfully indexed documents";
} catch (Exception $e) {
    logger()->error("Document indexing failed: " . $e->getMessage());
}
```