# Sigmie Documentation

Welcome to the complete documentation for Sigmie, the PHP library that makes Elasticsearch simple and powerful.

## What is Sigmie?

Sigmie is a PHP library designed to simplify working with Elasticsearch. Instead of writing complex Elasticsearch queries, you can use Sigmie's intuitive API to create powerful search experiences with features like typo tolerance, semantic search, faceting, and more.

## Quick Start

```php
use Sigmie\Sigmie;
use Sigmie\Mappings\NewProperties;
use Sigmie\Document\Document;

// Connect to Elasticsearch
$sigmie = Sigmie::create(['127.0.0.1:9200']);

// Define your data structure
$properties = new NewProperties;
$properties->title('title');
$properties->name('director'); 
$properties->number('year')->integer();

// Create an index
$sigmie->newIndex('movies')->properties($properties)->create();

// Add documents
$movies = $sigmie->collect('movies', refresh: true);
$movies->merge([
    new Document(['title' => 'The Matrix', 'director' => 'The Wachowskis', 'year' => 1999]),
    new Document(['title' => 'Inception', 'director' => 'Christopher Nolan', 'year' => 2010])
]);

// Search with advanced features
$response = $sigmie->newSearch('movies')
    ->properties($properties)
    ->queryString('matrix')
    ->typoTolerance()
    ->highlighting(['title'])
    ->get();
```

## Documentation Structure

### Getting Started

- **[Installation](installation.md)** - Install and configure Sigmie
- **[Getting Started](getting-started.md)** - Your first Sigmie application
- **[Core Concepts](core-concepts.md)** - Understand the fundamentals

### Core Features

- **[Index Management](index.md)** - Create and manage indices
- **[Document Management](document.md)** - Work with documents and collections
- **[Property Mappings](mappings.md)** - Define field types and behaviors
- **[Search](search.md)** - High-level search API with built-in features
- **[Query Builder](query.md)** - Low-level query construction

### Advanced Features

- **[Analysis](analysis.md)** - Text processing and tokenization
- **[Language Support](language.md)** - Multi-language search capabilities
- **[Semantic Search](semantic-search.md)** - Vector-based search
- **[RAG (Retrieval-Augmented Generation)](rag.md)** - LLM-powered intelligent answers
- **[Facets & Aggregations](facets.md)** - Faceted search interfaces
- **[Filter Parser](filter-parser.md)** - Advanced filtering syntax
- **[Sort Parser](sort-parser.md)** - Flexible sorting options

### Text Processing

- **[Tokenizers](tokenizers.md)** - Text tokenization methods
- **[Token Filters](token-filters.md)** - Token transformation filters  
- **[Character Filters](char-filters.md)** - Character-level processing

### Integration & Deployment

- **[Laravel Scout](laravel-scout.md)** - Laravel integration
- **[Testing](testing.md)** - Test your search functionality
- **[Docker](docker.md)** - Containerized deployments

### Reference

- **[API Reference](api-reference.md)** - Complete API documentation
- **[Packages](packages.md)** - Individual package information
- **[Update Guide](update.md)** - Migration between versions
- **[Template](template.md)** - Documentation template

## Key Features

### 🔍 **Powerful Search**
- Full-text search with relevance scoring
- Typo tolerance and fuzzy matching
- Multi-field searching with custom weights
- Semantic search using vector embeddings

### 🤖 **AI-Powered Features**
- Retrieval-Augmented Generation (RAG) for intelligent answers
- Streaming responses for real-time user experiences
- Vector embeddings and semantic search
- LLM integration with reranking capabilities

### 📝 **Document Management**  
- Type-safe document creation and validation
- Bulk operations for performance
- Flexible data structures with nested objects
- Random document sampling

### 🏗️ **Index Management**
- Flexible mappings with high-level field types
- Multi-language analysis support
- Index templates and settings
- Zero-downtime index updates

### 🎯 **Advanced Filtering**
- Intuitive filter syntax (`year>1990 AND rating>=8.0`)
- Faceted search with aggregations
- Geographic and range filtering
- Complex boolean logic

### 🚀 **Developer Experience**
- Fluent, chainable API
- Comprehensive error handling
- Built-in debugging and logging
- Laravel Scout integration

## System Requirements

- **PHP >= 8.1**
- **Elasticsearch ^7** or **^8**
- **Composer** for package management

## Installation

```bash
composer require sigmie/sigmie
```

## Basic Usage Patterns

### E-commerce Search

```php
$response = $sigmie->newSearch('products')
    ->properties($properties)
    ->queryString('wireless headphones')
    ->filters('in_stock:true AND price<200')
    ->facets('category brand')
    ->sort('_score:desc price:asc')
    ->highlighting(['name', 'description'])
    ->get();
```

### Content Management

```php
$response = $sigmie->newSearch('articles')
    ->properties($properties)
    ->queryString('elasticsearch tutorial')
    ->filters('is_published:true AND author_id:123')
    ->sort('published_at:desc')
    ->from(20)
    ->size(10)
    ->get();
```

### Semantic Search

```php
$response = $sigmie->newSearch('documents')
    ->properties($properties)
    ->semantic()
    ->queryString('artificial intelligence machine learning')
    ->get();
```

### AI-Powered Question Answering

```php
use Sigmie\AI\LLMs\OpenAILLM;

$llm = new OpenAILLM('your-openai-api-key');

// Streaming response for real-time experience
$stream = $sigmie->newRag($llm)
    ->search(
        $sigmie->newSearch('knowledge-base')
            ->queryString('What is machine learning?')
            ->size(5)
    )
    ->prompt(function ($prompt) {
        $prompt->question('What is machine learning?');
        $prompt->contextFields(['title', 'content']);
    })
    ->instructions('You are a helpful technical assistant.')
    ->answer(stream: true);

foreach ($stream as $chunk) {
    echo $chunk;
    flush();
}
```

## Common Use Cases

### User-Facing Search
Build search experiences with typo tolerance, highlighting, and faceted navigation.

### Content Discovery
Enable semantic search to find content by meaning, not just keywords.

### Intelligent Q&A Systems
Create AI-powered question-answering systems that provide contextually accurate responses using your own data.

### Data Analysis
Use aggregations and facets to analyze and summarize your data.

### Real-time Search
Implement autocomplete and search-as-you-type functionality.

## Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Application   │───▶│     Sigmie      │───▶│  Elasticsearch  │
│                 │    │                 │    │                 │
│ • Models        │    │ • Search        │    │ • Indices       │
│ • Controllers   │    │ • Query         │    │ • Documents     │
│ • Views         │    │ • Index         │    │ • Mappings      │
└─────────────────┘    │ • Document      │    └─────────────────┘
                       │ • Properties    │           ▲
                       │ • RAG           │           │
                       └─────────────────┘           │
                               │                     │
                               ▼                     │
                       ┌─────────────────┐           │
                       │   AI Services   │───────────┘
                       │                 │
                       │ • OpenAI        │
                       │ • Embeddings    │
                       │ • Rerankers     │
                       └─────────────────┘
```

## Support & Community

- **Issues**: [GitHub Issues](https://github.com/sigmie/sigmie/issues)
- **Discussions**: [GitHub Discussions](https://github.com/sigmie/sigmie/discussions)
- **Email**: For security issues, email nico@sigmie.com

## Contributing

Sigmie is open-source software. Contributions are welcome! Please see our contributing guidelines for details on how to contribute.

## License

Sigmie is released under the MIT License. See the LICENSE file for details.

---

**Ready to get started?** Begin with the [Installation Guide](installation.md) or jump straight into the [Getting Started Tutorial](getting-started.md).

For experienced Elasticsearch users, the [API Reference](api-reference.md) provides comprehensive documentation of all available methods and classes.

To explore AI-powered search capabilities, check out the [RAG Documentation](rag.md) for intelligent question-answering systems.