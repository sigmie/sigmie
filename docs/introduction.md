# Introduction to Sigmie

Sigmie is a powerful PHP library designed to make Elasticsearch accessible and easy to use. Whether you're building a simple search feature or a complex data analysis system, Sigmie provides the tools you need to create fast, relevant search experiences.

## The Problem with Elasticsearch

Elasticsearch is incredibly powerful, but it can be challenging to work with directly:

- **Complex Query DSL**: Writing Elasticsearch queries requires deep knowledge of its JSON-based query language
- **Configuration Overhead**: Setting up indices with proper mappings and analysis requires expertise
- **Learning Curve**: Understanding concepts like analyzers, tokenizers, and scoring can be overwhelming
- **Boilerplate Code**: Common tasks require repetitive, error-prone code

## The Sigmie Solution

Sigmie solves these problems by providing a high-level, intuitive PHP API that encapsulates years of Elasticsearch best practices:

```php
// Instead of complex JSON queries...
$complexElasticsearchQuery = [
    'query' => [
        'bool' => [
            'must' => [
                ['multi_match' => [
                    'query' => 'star wars',
                    'fields' => ['title^3', 'description']
                ]]
            ],
            'filter' => [
                ['range' => ['year' => ['gte' => 1990]]],
                ['term' => ['is_available' => true]]
            ]
        ]
    ],
    'highlight' => [
        'fields' => ['title' => new \stdClass()]
    ]
];

// Use Sigmie's intuitive API
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('star wars')
    ->weight(['title' => 3, 'description' => 1])
    ->filters('year>=1990 AND is_available:true')
    ->highlighting(['title'])
    ->get();
```

## Key Benefits

### 🎯 **Simplicity**
Write expressive code that clearly shows your intent. Sigmie's fluent API reads like natural language.

### 🚀 **Performance**
Built-in optimizations and best practices ensure your searches are fast and efficient.

### 🔧 **Flexibility**
From simple text searches to complex semantic matching, Sigmie scales with your needs.

### 🛡️ **Type Safety**
Strong typing and validation prevent common errors and make your code more reliable.

### 📚 **Rich Features**
Advanced capabilities like typo tolerance, semantic search, and faceting work out of the box.

## Core Philosophy

Sigmie is built around several key principles:

### **Convention over Configuration**
Sensible defaults mean you can get started quickly, but everything is customizable when you need it.

### **Composable Building Blocks**
Combine simple components to create complex search experiences.

### **Elasticsearch Native**
Leverage the full power of Elasticsearch without being locked into a specific abstraction.

### **Developer Experience**
Clear error messages, comprehensive documentation, and intuitive APIs make development enjoyable.

## Basic Example

Let's see Sigmie in action with a simple movie search:

### 1. Define Your Data Structure

```php
use Sigmie\Mappings\NewProperties;

$properties = new NewProperties;
$properties->title('title');      // Movie titles
$properties->name('director');    // Director names
$properties->category('genre');   // Movie genres
$properties->number('year')->integer();
$properties->number('rating')->float();
$properties->longText('description');
```

### 2. Create an Index

```php
$sigmie->newIndex('movies')
    ->properties($properties)
    ->lowercase()           // Convert text to lowercase
    ->tokenizeOnWhitespaces() // Split on spaces
    ->create();
```

### 3. Add Your Data

```php
use Sigmie\Document\Document;

$movies = $sigmie->collect('movies', refresh: true);
$movies->merge([
    new Document([
        'title' => 'The Matrix',
        'director' => 'The Wachowskis',
        'genre' => 'Sci-Fi',
        'year' => 1999,
        'rating' => 8.7,
        'description' => 'A computer programmer discovers that reality as he knows it is a simulation controlled by machines.'
    ]),
    new Document([
        'title' => 'Inception', 
        'director' => 'Christopher Nolan',
        'genre' => 'Sci-Fi',
        'year' => 2010,
        'rating' => 8.8,
        'description' => 'A thief who steals corporate secrets through dream-sharing technology is given the inverse task of planting an idea.'
    ])
]);
```

### 4. Search with Advanced Features

```php
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matrix simulation')
    ->typoTolerance()                    // Handle spelling mistakes
    ->highlighting(['title', 'description']) // Highlight matches
    ->weight(['title' => 3, 'description' => 1]) // Title is more important
    ->filters('year>1990 AND rating>=8.0')      // Filter results
    ->sort('rating:desc')                        // Sort by rating
    ->get();
```

### 5. Process Results

```php
$hits = $response->json('hits.hits');

foreach ($hits as $hit) {
    $movie = $hit['_source'];
    echo "{$movie['title']} ({$movie['year']}) - Rating: {$movie['rating']}\n";
    
    // Show highlighted text
    if (isset($hit['highlight']['description'])) {
        echo "Match: " . $hit['highlight']['description'][0] . "\n";
    }
}
```

## What Makes This Special?

### **Intelligent Field Types**
Instead of raw Elasticsearch field types, use semantic types like `title()`, `name()`, and `category()` that come pre-configured for their specific use cases.

### **Natural Filter Syntax**
Write filters like `'year>1990 AND rating>=8.0'` instead of complex nested JSON structures.

### **Built-in Best Practices**
Features like typo tolerance, semantic search, and proper text analysis are easy to enable and configure.

### **Seamless Laravel Integration**
Use Sigmie with Laravel Scout for effortless integration with your Eloquent models.

## Advanced Capabilities

As your needs grow, Sigmie provides advanced features:

### **Semantic Search**
Find content by meaning, not just keywords:

```php
$response = $sigmie->newSearch('articles')
    ->semantic()
    ->queryString('artificial intelligence')
    ->get();
```

### **Complex Querying**
Build sophisticated queries with boolean logic:

```php
$response = $sigmie->newQuery('products')
    ->bool(function($bool) {
        $bool->must()->match('name', 'laptop');
        $bool->filter()->range('price', ['<=' => 1500]);
        $bool->should()->term('brand', 'apple');
    })
    ->get();
```

### **Multi-language Support**
Built-in analyzers for different languages:

```php
$sigmie->newIndex('articles')
    ->language(new German())
    ->germanNormalize()
    ->create();
```

### **Faceted Search**
Build rich filtering interfaces:

```php
$response = $sigmie->newSearch('products')
    ->queryString('laptop')
    ->facets('brand category price:100')
    ->get();

$facets = $response->json('facets');
```

## Real-World Applications

Sigmie is used to build:

- **E-commerce search** with faceted navigation and typo tolerance
- **Content management systems** with full-text search and categorization
- **Documentation sites** with semantic search and autocomplete
- **Data analytics dashboards** with aggregations and filtering
- **Knowledge bases** with semantic similarity matching

## Learning Path

1. **[Installation](installation.md)** - Set up Sigmie and Elasticsearch
2. **[Getting Started](getting-started.md)** - Build your first search application
3. **[Core Concepts](core-concepts.md)** - Understand the fundamentals
4. **[Index Management](index.md)** - Master index creation and configuration
5. **[Document Management](document.md)** - Work with collections and documents
6. **[Search Features](search.md)** - Explore advanced search capabilities
7. **[Laravel Integration](laravel-scout.md)** - Connect with your Laravel app

## System Requirements

- **PHP >= 8.1**
- **Elasticsearch ^7** or **^8**
- **Composer** for dependency management

## What's Next?

The documentation is designed to take you from beginner to expert:

- **New to Elasticsearch?** Start with [Getting Started](getting-started.md)
- **Experienced developer?** Jump to the [API Reference](api-reference.md)
- **Laravel user?** See the [Laravel Scout Integration](laravel-scout.md)
- **Building production systems?** Check out [Performance and Scaling](installation.md#performance-optimization)

Ready to build amazing search experiences? Let's [get started](installation.md)!

## Getting Help

- **Documentation**: You're reading it! Each section includes examples and best practices
- **GitHub Issues**: Report bugs and request features
- **Community**: Join discussions with other Sigmie users
- **Security**: Email security vulnerabilities to nico@sigmie.com

Sigmie makes the power of Elasticsearch accessible to every PHP developer. Whether you're building a simple blog search or a complex e-commerce platform, Sigmie provides the tools you need to create fast, relevant search experiences your users will love.