# Recommendations

## What are Recommendations?

Recommendations allow you to build "similar items" queries by using the actual vector embeddings from seed documents. This feature is designed for use cases like:

- "You might also like..." product suggestions
- "Related content" article recommendations
- "Similar properties" real estate listings
- "Customers who viewed this also viewed..." patterns
- Content discovery based on user interests

The recommendations API uses advanced techniques like **Reciprocal Rank Fusion (RRF)** and **Maximal Marginal Relevance (MMR)** to provide high-quality, diverse results.

## When to Use Recommendations

Use the Recommendations API when you need to:

- Find items similar to one or more seed documents
- Use actual document embeddings rather than generating new ones from text
- Combine results from multiple seed documents intelligently
- Diversify results to avoid showing too-similar items (MMR)
- Weight different semantic fields differently

For general search queries where users are entering search terms, use `NewSearch` instead.

## Basic Usage

```php
use Sigmie\Mappings\NewProperties;

// Define your index properties with semantic fields
$blueprint = new NewProperties();
$blueprint->text('name')->semantic();
$blueprint->text('category')->semantic();
$blueprint->text('description')->semantic();
$blueprint->number('price');

// Get recommendations based on seed document(s)
$recommendations = $sigmie->newRecommend($indexName)
    ->properties($blueprint)
    ->seedIds(['product-123', 'product-456'])  // Use existing documents as seeds
    ->field('category', weight: 2.0)            // Category matters most
    ->field('name', weight: 1.0)                // Name matters less
    ->filter('price<=100')
    ->topK(5)
    ->hits();

// Results: Items similar to the seed products, weighted by field importance
```

### How It Works

1. **Seed Documents**: You provide IDs of existing documents in your index
2. **Vector Extraction**: The system extracts embeddings from those documents
3. **Multi-Search**: For each seed document, a semantic search is performed
4. **RRF Fusion**: Results from all searches are combined using Reciprocal Rank Fusion
5. **Field Weighting**: Each field's importance is controlled by its weight
6. **MMR (Optional)**: Diversify results to avoid redundancy

## Field Weighting System

The weighting system allows you to control how much each field influences the final recommendations. Higher weights give more importance to that field's similarity.

### How Weights Work

Each `field()` specifies a semantic field to use from the seed documents along with its weight. The system:

1. Extracts vectors for that field from each seed document
2. Creates semantic searches using those actual vectors (no new embeddings generated)
3. Applies the weight as a multiplier when scoring results
4. Combines results using RRF (Reciprocal Rank Fusion)

```php
$sigmie->newRecommend($indexName)
    ->properties($blueprint)
    ->seedIds(['product-42'])
    ->field('category', weight: 3.0)     // Most important
    ->field('brand', weight: 2.0)        // Important
    ->field('description', weight: 1.0)  // Least important
    ->topK(10)
    ->hits();
```

In this example:
- The category field from product-42 has 3x influence
- The brand field has 2x influence
- The description field has standard influence

### Weight Guidelines

- **1.0** - Standard weight (baseline)
- **2.0-3.0** - Important fields that should strongly influence results
- **0.5** - Less important fields that provide minor refinement
- **5.0+** - Dominant fields that should heavily outweigh others

### Practical Examples

**E-commerce Product Recommendations:**
```php
// Recommend similar products where category matters most
// Based on what a user is currently viewing
$recommendations = $sigmie->newRecommend('products')
    ->properties($productProperties)
    ->seedIds(['nike-running-shoe-123'])  // Product user is viewing
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->field('color', weight: 1.0)
    ->filter('in_stock:true AND price<=200')
    ->topK(8)
    ->hits();
```

**Blog Article Recommendations:**
```php
// Find similar articles based on what user just read
$recommendations = $sigmie->newRecommend('articles')
    ->properties($articleProperties)
    ->seedIds(['article-ml-intro-456'])
    ->field('tags', weight: 2.5)
    ->field('title', weight: 1.5)
    ->field('content', weight: 1.0)
    ->filter('published:true')
    ->topK(5)
    ->hits();
```

**Multiple Seed Documents:**
```php
// Recommend based on user's browsing history
$recommendations = $sigmie->newRecommend('products')
    ->properties($productProperties)
    ->seedIds([
        'product-last-viewed',
        'product-previously-viewed-1',
        'product-previously-viewed-2'
    ])
    ->field('category', weight: 3.0)
    ->field('tags', weight: 2.0)
    ->filter('in_stock:true')
    ->topK(10)
    ->hits();
```

## Available Methods

### `properties(Properties|NewProperties $properties)`

Set the index properties/mappings. This is required to determine which fields are semantic.

```php
$blueprint = new NewProperties();
$blueprint->text('title')->semantic();
$blueprint->text('category')->semantic(accuracy: 4);

$recommendations->properties($blueprint);
```

Only fields marked as semantic can be used in recommendations.

### `seedIds(array $documentIds)`

Specify the seed documents to base recommendations on. The system will extract embeddings from these documents.

```php
// Single seed document
$recommendations->seedIds(['product-123']);

// Multiple seed documents (RRF will fuse results)
$recommendations->seedIds(['product-123', 'product-456', 'product-789']);
```

**Important:**
- Documents must exist in the index
- Documents must have the `embeddings` field populated
- Use `retrieveEmbeddingsField()` when indexing to ensure embeddings are stored

### `field(string $fieldName, float $weight = 1.0)`

Specify which semantic field to use from the seed documents and its importance weight.

```php
// Single field
$recommendations->field('category', weight: 2.0);

// Multiple fields
$recommendations
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->field('description', weight: 1.0);
```

**Important:** Only semantic fields will be used. Non-semantic fields are automatically skipped.

### `filter(string $filter)`

Add filter expressions to narrow down results using Sigmie's filter syntax.

```php
// Price range
$recommendations->filter('price>=50 AND price<=200');

// Boolean fields
$recommendations->filter('in_stock:true');

// Multiple conditions
$recommendations->filter('price<=100 AND in_stock:true AND rating>=4');
```

See the [Filter Parser documentation](/docs/filter-parser.md) for complete filter syntax.

### `topK(int $k)`

Set the number of results to return. Default is 10.

```php
$recommendations->topK(5);  // Return top 5 recommendations
```

### `rrf(int $rankConstant = 60, int $rankWindowSize = 10)`

Configure Reciprocal Rank Fusion parameters for combining results from multiple searches.

```php
// Use default RRF settings
$recommendations->rrf();

// Custom RRF settings
$recommendations->rrf(rankConstant: 60, rankWindowSize: 10);
```

The `rankConstant` parameter controls how quickly scores decrease with rank position. Higher values make the fusion more forgiving of lower-ranked results.

### `mmr(float $lambda = 0.5)`

Enable Maximal Marginal Relevance for result diversification.

```php
// Enable MMR with default lambda (0.5 - balanced relevance and diversity)
$recommendations->mmr();

// Favor relevance over diversity
$recommendations->mmr(lambda: 0.8);

// Favor diversity over relevance
$recommendations->mmr(lambda: 0.2);
```

**Lambda parameter:**
- `1.0` - Pure relevance (no diversity)
- `0.5` - Balanced (default)
- `0.0` - Pure diversity (maximum variety)

See the [MMR section](#maximal-marginal-relevance-mmr) for detailed explanation.

### `make()`

Build and return the underlying `Search` object without executing it.

```php
$search = $recommendations->make();
$rawQuery = $search->toRaw();  // Inspect the generated Elasticsearch query
```

This is useful for debugging or when you need to modify the search before executing.

### `get()`

Execute the search and return the full Elasticsearch response.

```php
$response = $recommendations->get();
$hits = $response->hits();
$total = $response->total();
```

### `hits()`

Execute the search and return just the hits array. This is the most common method.

```php
$hits = $recommendations->hits();

foreach ($hits as $hit) {
    echo $hit['_source']['name'];
    echo $hit['_score'];  // Similarity score
}
```

## Requirements

### Semantic Fields

All fields used in recommendations must be configured as semantic fields in your index properties.

```php
$blueprint = new NewProperties();

// Correct: Semantic fields can be used in recommendations
$blueprint->text('name')->semantic();
$blueprint->text('category')->semantic(accuracy: 4, dimensions: 256);

// Incorrect: Regular fields will be skipped
$blueprint->text('description');  // Not semantic, will be ignored
```

If you specify a non-semantic field in `field()`, it will be automatically skipped without throwing an error.

### Embeddings API

The embeddings API must be configured on your Sigmie instance:

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;

$embeddingsApi = new OpenAIEmbeddingsApi('your-api-key');
$sigmie = $sigmie->embedder($embeddingsApi);

// Now you can use recommendations
$recommendations = $sigmie->newRecommend($indexName);
```

See the [Semantic Search documentation](/docs/semantic-search.md) for more details on setting up embeddings.

## How It Works Internally

Understanding the internals helps you use recommendations effectively.

### Vector Extraction from Seed Documents

The recommendations system uses actual document embeddings rather than generating new ones:

```php
$recommendations = $sigmie->newRecommend('products')
    ->seedIds(['product-123', 'product-456'])
    ->field('category', weight: 2.0)
    ->field('description', weight: 1.0);
```

**What happens:**

1. **Retrieve Seed Documents**: Fetches documents with IDs `product-123` and `product-456`
2. **Extract Vectors**: For each document and field, extracts vectors from `embeddings.{field}`:
   ```php
   $vectors = dot($doc->_source['embeddings'])->get('category');
   // Returns all vector variants for that field (different dimensions/strategies)
   ```
3. **Multi-Search**: For each seed document, creates searches using the extracted vectors
4. **Per-Field Queries**: Each field specified gets its own set of vector queries with the specified weight

### Reciprocal Rank Fusion (RRF)

RRF combines multiple ranked lists into a single ranked list. This is how results from multiple seed documents and fields are merged.

**The RRF Formula:**

For each document, the score is:
```
score = Σ (1 / (k + rank))
```

Where:
- `k` is the rank constant (default: 60)
- `rank` is the document's position in that particular result list
- The sum is across all result lists where the document appears

**Example:**

If a document appears as:
- Rank 1 in seed document A's results: `1 / (60 + 1) = 0.0164`
- Rank 3 in seed document B's results: `1 / (60 + 3) = 0.0159`
- Total RRF score: `0.0164 + 0.0159 = 0.0323`

**Benefits:**
- Documents appearing in multiple result sets get higher scores
- Robust to outliers and varying score scales
- No normalization required
- Simple and effective

**Configuration:**

```php
$recommendations->rrf(
    rankConstant: 60,    // Higher = more forgiving of lower ranks
    rankWindowSize: 10   // (Currently unused, reserved for future use)
);
```

### Per-Field Processing

When multiple fields are specified, the system processes each field independently:

```php
->field('category', weight: 3.0)
->field('brand', weight: 2.0)
```

**Process:**

1. **Separate Searches**: Each field gets its own searches across all seed documents
2. **RRF per Field**: Results for each field are fused using RRF
3. **MMR per Field (if enabled)**: Each field's fused results are diversified independently
4. **Final RRF**: All per-field results are combined with a final RRF fusion

This multi-stage approach ensures:
- Field weights are properly respected
- Each field contributes diverse results
- Final results balance all fields according to weights

### Maximal Marginal Relevance (MMR)

MMR diversifies results to avoid showing too-similar items. Without MMR, you might get 10 slightly different variants of the same product. With MMR, you get a diverse set of relevant recommendations.

**The MMR Algorithm:**

For each position in the result list:
1. Calculate **relevance**: Cosine similarity to query (seed document centroid)
2. Calculate **diversity**: Maximum similarity to already-selected results
3. Compute MMR score: `λ × relevance - (1-λ) × diversity`
4. Select the document with highest MMR score
5. Repeat until topK results are selected

**The Lambda Parameter:**

Controls the trade-off between relevance and diversity:

```php
// Pure relevance (no diversity) - might get very similar items
$recommendations->mmr(lambda: 1.0);

// Balanced (default) - good mix of relevance and variety
$recommendations->mmr(lambda: 0.5);

// Pure diversity - maximum variety, less relevant
$recommendations->mmr(lambda: 0.0);
```

**When to Use MMR:**

✅ **Good for:**
- E-commerce product recommendations (avoid showing 10 similar products)
- Content discovery (show diverse articles, not just slight variations)
- Music/video recommendations (variety in playlists)
- Any case where user wants to explore options

❌ **Skip when:**
- Precision is critical (medical, legal search)
- Results need to be nearly identical (finding exact matches)
- Small result sets (< 5 items)

**Example:**

```php
// Without MMR - might return 10 nearly identical blue Nike shoes
$recommendations = $sigmie->newRecommend('products')
    ->seedIds(['blue-nike-running-shoe'])
    ->field('category', weight: 2.0)
    ->field('color', weight: 1.0)
    ->topK(10)
    ->hits();

// With MMR - returns blue Nike shoes, but also other brands, styles, colors
$recommendations = $sigmie->newRecommend('products')
    ->seedIds(['blue-nike-running-shoe'])
    ->field('category', weight: 2.0)
    ->field('color', weight: 1.0)
    ->mmr(lambda: 0.5)  // Balanced diversity
    ->topK(10)
    ->hits();
```

**Per-Field MMR:**

When MMR is enabled, it's applied independently to each field's results before final fusion:

1. Category field results → MMR diversification
2. Brand field results → MMR diversification
3. Description field results → MMR diversification
4. All diversified lists → Final RRF fusion

This ensures each field contributes diverse results, and the final output is well-balanced across all dimensions.

**Performance Note:**

MMR requires computing similarities between all candidates, which is O(n²). For optimal performance:
- Use reasonable topK values (10-20)
- Apply filters to reduce the candidate pool
- The system automatically retrieves `topK × 10` candidates before MMR

## Recommendations vs NewSearch

Both APIs can perform semantic searches, but they serve different purposes:

### Use NewRecommendations When

- You want to find items similar to existing documents
- You have document IDs to use as seeds
- You need different weights for different fields
- You want result fusion from multiple seed documents (RRF)
- You want diversity in results (MMR)
- You're building "more like this" or "similar items" features

**Example:**
```php
// Find products similar to what user is viewing
$sigmie->newRecommend('products')
    ->seedIds(['current-product-id'])
    ->field('category', weight: 2.0)
    ->field('brand', weight: 1.5)
    ->mmr(lambda: 0.5)  // Add diversity
    ->topK(5)
    ->hits();
```

### Use NewSearch When

- Users are entering search queries (text input)
- You need keyword search combined with semantic search
- You're generating embeddings from user query text
- You want more control over the query structure
- You need advanced features like aggregations, highlighting, or autocomplete

**Example:**
```php
// User search query
$sigmie->newSearch('products')
    ->queryString('red kitchen appliances')
    ->semantic()
    ->size(20)
    ->filters('price<=500')
    ->get();
```

### Key Differences

| Feature | NewRecommendations | NewSearch |
|---------|-------------------|-----------|
| Primary use case | Similar items from seeds | User text queries |
| Input | Document IDs | Query string |
| Embedding source | Existing document vectors | Generated from query text |
| Semantic mode | Always enabled | Optional |
| Keyword search | Disabled | Optional |
| Field weights | Per-field weights | Global weighting |
| Multi-document | RRF fusion | N/A |
| Diversity | MMR support | No built-in diversity |
| API complexity | Purpose-built | General-purpose |

## Complete Examples

### E-commerce Product Recommendations

```php
use Sigmie\AI\APIs\OpenAIEmbeddingsApi;
use Sigmie\Mappings\NewProperties;

// Set up
$embeddingApi = new OpenAIEmbeddingsApi(getenv('OPENAI_API_KEY'));
$sigmie = $sigmie->embedder($embeddingApi);

// Define product properties
$blueprint = new NewProperties();
$blueprint->text('name')->semantic();
$blueprint->text('category')->semantic(accuracy: 4);
$blueprint->text('description')->semantic(accuracy: 2, dimensions: 512);
$blueprint->text('brand')->semantic();
$blueprint->number('price');
$blueprint->number('rating');
$blueprint->bool('in_stock');

// User is viewing a MacBook Pro - get similar products with diversity
$currentProductId = 'macbook-pro-16-2023';

$recommendations = $sigmie->newRecommend('products')
    ->properties($blueprint)
    ->seedIds([$currentProductId])
    ->field('category', weight: 3.0)
    ->field('brand', weight: 2.0)
    ->field('name', weight: 1.5)
    ->field('description', weight: 1.0)
    ->mmr(lambda: 0.6)  // Favor relevance slightly over diversity
    ->filter('in_stock:true AND price<=2000 AND rating>=4')
    ->topK(10)
    ->hits();

echo "You might also like:\n\n";
foreach ($recommendations as $hit) {
    $product = $hit['_source'];
    echo "• {$product['name']} - {$product['brand']}\n";
    echo "  {$product['category']} | \${$product['price']}\n";
    echo "  Rating: {$product['rating']}/5 | Score: {$hit['_score']}\n\n";
}
```

### Content Discovery System

```php
// Article properties
$blueprint = new NewProperties();
$blueprint->text('title')->semantic();
$blueprint->text('content')->semantic(accuracy: 2, dimensions: 512);
$blueprint->text('tags')->semantic();
$blueprint->text('author');
$blueprint->date('published_at');
$blueprint->number('views');

// User just read an article about machine learning
$currentArticleId = 'intro-to-deep-learning-2024';

// Find related articles with good diversity
$relatedArticles = $sigmie->newRecommend('articles')
    ->properties($blueprint)
    ->seedIds([$currentArticleId])
    ->field('tags', weight: 3.0)
    ->field('title', weight: 2.0)
    ->field('content', weight: 1.0)
    ->mmr(lambda: 0.5)  // Balanced diversity
    ->filter('published_at>=2023-01-01 AND views>100')
    ->topK(6)
    ->hits();

echo "Related Articles:\n\n";
foreach ($relatedArticles as $hit) {
    $article = $hit['_source'];
    echo "• {$article['title']}\n";
    echo "  by {$article['author']} | {$article['views']} views\n\n";
}
```

### Multi-Category Recommendations

```php
// Properties with multiple semantic fields
$blueprint = new NewProperties();
$blueprint->text('name')->semantic();
$blueprint->text('primary_category')->semantic();
$blueprint->text('secondary_category')->semantic();
$blueprint->text('tags')->semantic();
$blueprint->number('price');

// User is viewing a yoga mat - recommend based on multiple category levels
$currentProductId = 'premium-yoga-mat-purple';

// The seed document has these values:
// - primary_category: "Sports & Outdoors"
// - secondary_category: "Yoga"
// - tags: "fitness wellness exercise meditation"
// - name: "Premium Yoga Mat"

$recommendations = $sigmie->newRecommend('products')
    ->properties($blueprint)
    ->seedIds([$currentProductId])
    ->field('primary_category', weight: 4.0)
    ->field('secondary_category', weight: 3.0)
    ->field('tags', weight: 1.5)
    ->field('name', weight: 1.0)
    ->filter('price<=150')
    ->topK(8)
    ->hits();

// Results: Similar products in yoga/fitness category with balanced weighting
```

### Recommendation Widget Implementation

```php
/**
 * Get product recommendations for a "You might also like" widget
 */
function getProductRecommendations(
    Sigmie $sigmie,
    Properties $properties,
    string $currentProductId,
    int $limit = 4
): array {
    return $sigmie->newRecommend('products')
        ->properties($properties)
        ->seedIds([$currentProductId])
        ->field('category', weight: 3.0)
        ->field('name', weight: 1.5)
        ->filter('in_stock:true')
        ->topK($limit)
        ->hits();
}

// Usage
$currentProductId = 'wireless-headphones-sony-wh1000xm5';

$recommendations = getProductRecommendations(
    $sigmie,
    $productProperties,
    $currentProductId,
    4
);

// Display in widget
echo '<div class="recommendations">';
echo '<h3>You might also like</h3>';
foreach ($recommendations as $hit) {
    $product = $hit['_source'];
    echo "<div class='product'>";
    echo "<h4>{$product['name']}</h4>";
    echo "<p>\${$product['price']}</p>";
    echo "</div>";
}
echo '</div>';
```

## Best Practices

### Field Selection

Choose fields that meaningfully represent similarity:

- **Good:** category, brand, product_type, tags, features
- **Less useful:** id, created_at, stock_quantity, internal_codes

### Weight Tuning

Start with these guidelines and adjust based on results:

1. Identify the most important similarity dimension (weight: 3-4)
2. Add secondary fields (weight: 2)
3. Add refinement fields (weight: 1)

Test different weight combinations to find the best balance for your use case.

### Performance Optimization

- Limit `topK` to what you actually need (typically 5-10)
- Use filters to narrow the search space
- Configure appropriate semantic accuracy levels for each field
- Monitor embedding API usage and costs

### Result Quality

- Use filters to exclude the current item from recommendations
- Ensure semantic fields have appropriate accuracy settings
- Test with various seed values to verify quality
- Monitor user engagement with recommendations to iterate

### Error Handling

```php
try {
    $recommendations = $sigmie->newRecommend('products')
        ->properties($blueprint)
        ->seedIds(['current-product-id'])
        ->field('category', weight: 2.0)
        ->field('name', weight: 1.0)
        ->topK(5)
        ->hits();

    if (empty($recommendations)) {
        // Fallback: show popular items or default recommendations
        $recommendations = getPopularProducts($sigmie, 5);
    }
} catch (Exception $e) {
    // Log error and show fallback content
    error_log("Recommendations failed: " . $e->getMessage());
    $recommendations = getDefaultRecommendations();
}
```

## Debugging

### Inspect Generated Query

Use `make()` to see the generated Elasticsearch query:

```php
$search = $sigmie->newRecommend('products')
    ->properties($blueprint)
    ->seedIds(['running-shoes-nike-pegasus'])
    ->field('category', weight: 2.0)
    ->field('name', weight: 1.0)
    ->topK(5)
    ->make();

// Inspect the raw query
$rawQuery = $search->toRaw();
print_r($rawQuery);
```

### Verify Semantic Fields

```php
$semanticFields = $blueprint->get()
    ->nestedSemanticFields()
    ->filter(fn($field) => $field->isSemantic())
    ->map(fn($field) => $field->fullPath)
    ->toArray();

echo "Available semantic fields:\n";
print_r($semanticFields);
```

### Check Field Skipping

If a field is specified but not semantic, it will be skipped. You won't get an error, but the field won't contribute to recommendations. Check your properties configuration if results don't match expectations.

## Common Issues

### No Results Returned

**Causes:**
- Filters are too restrictive
- No items in index match the semantic similarity
- Index is empty or not properly configured

**Solutions:**
- Test without filters first
- Verify index has documents
- Check that semantic fields are properly indexed with embeddings

### Poor Recommendation Quality

**Causes:**
- Incorrect field weights
- Non-semantic fields specified (they're skipped)
- Low semantic accuracy settings
- Seed values don't match actual data patterns

**Solutions:**
- Adjust weights based on testing
- Verify all specified fields are semantic
- Increase accuracy for important fields
- Use seed values that exist in your dataset

### Slow Performance

**Causes:**
- Large `topK` value
- High-dimensional embeddings
- No filters to narrow search space

**Solutions:**
- Reduce `topK` to minimum needed
- Use appropriate embedding dimensions
- Add filters to reduce search space
- Consider index optimization

## Related Documentation

- [Semantic Search](/docs/semantic-search.md) - Understanding semantic fields and embeddings
- [NewSearch](/docs/search.md) - General search API
- [Filter Parser](/docs/filter-parser.md) - Filter syntax reference
- [Mappings](/docs/mappings.md) - Configuring index properties
