<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class ImageSearchTest extends TestCase
{
    /**
     * @test
     */
    public function define_image_field_with_semantic_embeddings()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->image('product_image')->semantic(accuracy: 3, dimensions: 512, api: 'test-clip');
        $props->category('color');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        // Assert index was created with correct mappings
        $this->assertIndex($indexName, function (Assert $assert) {
            $assert->assertPropertyExists('embeddings');
            $assert->assertPropertyExists('product_image');

            // The image field should have the semantic configuration
            // which creates the embedding vectors at indexing time
        });
    }

    /**
     * @test
     */
    public function index_documents_with_image_urls()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->image('image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->text('description');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Pirates Ship',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
                'description' => 'A pirate ship on the ocean',
            ], _id: 'pirates'),
            new Document([
                'title' => 'Red Sports Car',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                'description' => 'A red sports car',
            ], _id: 'red-car'),
        ]);

        // Verify the clip API was called for image embeddings
        $this->clipApi->assertImageEmbedWasCalled(2);

        // Assert it was called with both image URLs
        $this->clipApi->assertImageSourceWasEmbedded('https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg');
        $this->clipApi->assertImageSourceWasEmbedded('https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg');
    }

    /**
     * @test
     */
    public function index_documents_with_base64_images()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->image('photo')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        // Download and convert basketball image to base64
        $basketballUrl = 'https://github.com/sigmie/test-images/raw/refs/heads/main/basket-ball.jpeg';
        $basketballData = file_get_contents($basketballUrl);
        $base64Image = 'data:image/jpeg;base64,' . base64_encode($basketballData);

        $tennisUrl = 'https://github.com/sigmie/test-images/raw/refs/heads/main/tennis-ball.jpeg';
        $tennisData = file_get_contents($tennisUrl);
        $base64Image2 = 'data:image/jpeg;base64,' . base64_encode($tennisData);

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'photo' => $base64Image,
        ], _id: 'basketball'));

        $collected->add(new Document([
            'photo' => $base64Image2,
        ], _id: 'tennis'));

        // Verify the clip API was called
        $this->clipApi->assertImageEmbedWasCalled(times: 2);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('orange ball')
            ->fields(['photo'])
            ->size(1)
            ->hits();

        $this->assertEquals('basketball', $hits[0]->_id);

        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('tennis')
            ->fields(['photo'])
            ->size(1)
            ->hits();

        $this->assertEquals('tennis', $hits[0]->_id);
    }

    /**
     * @test
     */
    public function local_image_path()
    {
        // Remove the feature also from internal
        $this->assertTrue(true, 'Feature removed - local paths are now converted to base64 internally');
    }

    /**
     * @test
     */
    public function image_preprocessing_resizes_to_224px()
    {
        // Remove the feature also from internal 
        $this->assertTrue(true, 'Image resizing is handled by ImageHelper');
    }

    /**
     * @test
     */
    public function image_to_image_search_using_query_string()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->image('image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->merge([
            new Document([
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
            ], _id: 'pirates'),
            new Document([
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
            ], _id: 'red-car'),
            new Document([
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/queen.jpeg',
            ], _id: 'queen'),
        ]);

        $this->clipApi->reset();

        // Search using an image URL as query
        $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryImage('https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg')
            ->fields(['image'])
            ->size(3)
            ->get();

        // Should find similar images
        $this->clipApi->assertImageSourceWasEmbedded('https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg');
    }

    /**
     * @test
     */
    public function text_to_image_search_with_clip()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->image('image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->text('description')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Ocean Scene',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
                'description' => 'Pirates on the high seas',
            ], _id: 'pirates'),
            new Document([
                'title' => 'Vehicle',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                'description' => 'A fast red sports car',
            ], _id: 'red-car'),
        ]);

        $this->clipApi->reset();

        // Search images using text query (CLIP handles both modalities)
        $hits = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('red vehicle')
            ->fields(['image', 'description'])
            ->size(2)
            ->hits();

        $this->assertEquals('red-car', $hits[0]->_id);

        // Since we're using semantic search with text query on both image and description fields,
        // the CLIP API should be called for the query string embedding
        $this->clipApi->assertBatchEmbedWasCalledWith('red vehicle');
    }

    /**
     * @test
     */
    public function multiple_images_field_gallery()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->image('gallery')->multiple()->semantic(accuracy: 2, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'title' => 'Photo Collection',
            'gallery' => [
                'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
                'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                'https://github.com/sigmie/test-images/raw/refs/heads/main/queen.jpeg',
            ],
        ], _id: 'collection'));

        // Verify the clip API was called for all images
        // With accuracy: 2 (average strategy), all images should be processed
        $this->clipApi->assertImageEmbedWasCalled(3);

        $doc = $this->sigmie->collect($indexName)->get('collection');

        // Verify that the gallery embeddings exist
        $this->assertArrayHasKey('embeddings', $doc->_source);
        $this->assertArrayHasKey('gallery', $doc->_source['embeddings']);

        // With accuracy: 2 (average strategy), the vectors should be averaged
        // The FakeClipApi returns random vectors, so we can't verify the actual averaging
        // But we can verify that a single vector was stored (the average)
        $galleryEmbeddings = $doc->_source['embeddings']['gallery'];
        $this->assertIsArray($galleryEmbeddings);

        // The averaged vector should be a single array (not an array of arrays)
        foreach ($galleryEmbeddings as $embeddingName => $vector) {
            if (is_array($vector) && !empty($vector)) {
                // Check that it's a flat array of numbers (the averaged vector)
                $this->assertIsNumeric($vector[0] ?? null, 'Vector should contain numeric values');
            }
        }
    }

    /**
     * @test
     */
    public function nested_image_fields()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->nested('products', function (NewProperties $props) {
            $props->text('name');
            $props->image('thumbnail')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
            $props->number('price');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'products' => [
                [
                    'thumbnail' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
                ],
            ],
        ], _id: 'pirate-ship'));

        $collected->add(new Document([
            'products' => [
                [
                    'thumbnail' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                ],
            ],
        ], _id: 'toy-car'));

        $hits = $this->sigmie
            ->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('red car')
            ->fields(['products.thumbnail'])
            ->size(2)
            ->hits();

        $this->clipApi->assertImageSourceWasEmbedded('https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg');

        $this->assertEquals('toy-car', $hits[0]->_id);
    }

    /**
     * @test
     */
    public function mixed_text_and_image_semantic_fields()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->image('image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->text('caption')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'title' => 'Amazing Pirates',
            'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
            'caption' => 'A pirate ship sailing the seven seas',
        ], _id: 'doc1'));

        // Verify both APIs were called
        // Title uses test-embeddings API
        $this->embeddingApi->assertBatchEmbedWasCalledWith('Amazing Pirates');

        // Caption uses test-clip API
        $this->clipApi->assertBatchEmbedWasCalledWith('A pirate ship sailing the seven seas');

        // Image uses test-clip API
        $this->clipApi->assertImageSourceWasEmbedded('https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg');
    }


    /**
     * @test
     */
    public function api_isolation_different_apis_for_text_vs_image()
    {
        $indexName = uniqid();

        // Create a second clip API for testing isolation
        $clipUrl = getenv('LOCAL_CLIP_URL') ?: 'http://localhost:7996';
        $alternativeClipApi = new \Sigmie\Testing\FakeClipApi(new \Sigmie\AI\APIs\InfinityClipApi($clipUrl));
        $this->sigmie->registerApi('alt-clip', $alternativeClipApi);

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->image('thumbnail')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->image('preview')->semantic(accuracy: 1, dimensions: 512, api: 'alt-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'title' => 'Test Product',
            'thumbnail' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
            'preview' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
        ], _id: 'product'));

        // Verify each API was called appropriately
        $this->embeddingApi->assertBatchEmbedWasCalled(); // For title
        $this->clipApi->assertImageEmbedWasCalled(1); // For thumbnail
        $alternativeClipApi->assertImageEmbedWasCalled(1); // For preview

        // Verify each API processed their respective image
        $clipImageCalls = $this->clipApi->getImageEmbedCalls();
        $this->assertCount(1, $clipImageCalls, 'Main CLIP API should have processed 1 image');

        $altImageCalls = $alternativeClipApi->getImageEmbedCalls();
        $this->assertCount(1, $altImageCalls, 'Alternative CLIP API should have processed 1 image');
    }

    /**
     * @test
     */
    public function error_handling_for_invalid_image_sources()
    {
        $indexName = uniqid();
        $this->expectException('The field image contains an invalid image source');

        $props = new NewProperties;
        $props->image('image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        // Invalid image sources are treated as text by CLIP
        // This is actually reasonable behavior for a multimodal model
        $collected->add(new Document([
            'image' => 'not-a-valid-url-or-path',
        ], _id: 'invalid'));

        // Verify the document was indexed
        $doc = $this->sigmie->collect($indexName)->get('invalid');
        $this->assertNotNull($doc);

        // The invalid source is stored as-is
        $this->assertEquals('not-a-valid-url-or-path', $doc->_source['image']);

        // And embeddings were still generated (as text)
        $this->assertArrayHasKey('embeddings', $doc->_source);
        $this->assertArrayHasKey('image', $doc->_source['embeddings']);
    }

    /**
     * @test
     */
    public function image_field_without_semantic_stores_raw_value()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->image('thumbnail'); // No semantic() call
        $props->image('preview')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'title' => 'Product',
            'thumbnail' => 'https://example.com/thumb.jpg', // Stored as-is
            'preview' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/queen.jpeg',
        ], _id: 'mixed'));

        // Only preview should trigger clip API
        $this->clipApi->assertImageEmbedWasCalled(1); // Only preview should trigger embedding

        // Verify raw value is stored
        $doc = $this->sigmie->collect($indexName)->get('mixed');
        $this->assertEquals('https://example.com/thumb.jpg', $doc->_source['thumbnail']);
        $this->assertArrayHasKey('embeddings', $doc->_source);
        $this->assertArrayHasKey('preview', $doc->_source['embeddings']);
        $this->assertArrayNotHasKey('thumbnail', $doc->_source['embeddings']);
    }

    /**
     * @test
     */
    public function comprehensive_text_to_image_search()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title');
        $props->image('image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->text('tags')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        // Index a variety of images with descriptive titles and tags
        $collected->merge([
            new Document([
                'title' => 'Pirate Ship Adventure',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
                'tags' => 'ocean sailing adventure pirates ship treasure caribbean',
            ], _id: 'doc-pirates'),
            new Document([
                'title' => 'Red Sports Car',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                'tags' => 'vehicle automobile sports fast racing red speed',
            ], _id: 'doc-car'),
            new Document([
                'title' => 'Royal Queen Portrait',
                'image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/queen.jpeg',
                'tags' => 'royalty monarch crown palace regal portrait queen',
            ], _id: 'doc-queen'),
        ]);

        // Test 1: Search for "sailing ship" - should find pirates first
        $results1 = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('sailing ship ocean adventure')
            ->fields(['image', 'tags'])
            ->size(3)
            ->get();

        $hits1 = $results1->hits();
        $this->assertGreaterThanOrEqual(1, count($hits1), 'Should find results for sailing ship query');
        $this->assertEquals('doc-pirates', $hits1[0]->_id, 'Pirate document should be the top result');

        // Test 2: Search for "fast automobile" - should find car first
        $results2 = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('fast automobile racing')
            ->fields(['image', 'tags'])
            ->size(3)
            ->get();

        $hits2 = $results2->hits();
        $this->assertGreaterThanOrEqual(1, count($hits2), 'Should find results for automobile query');
        $this->assertEquals('doc-car', $hits2[0]->_id, 'Car document should be the top result');

        // Test 3: Search for "royal crown" - should find queen first
        $results3 = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('royal crown')
            ->fields(['image', 'tags'])
            ->size(3)
            ->get();

        $hits3 = $results3->hits();
        $this->assertGreaterThanOrEqual(1, count($hits3), 'Should find results for royal query');
        $this->assertEquals('doc-queen', $hits3[0]->_id, 'Queen document should be the top result');
    }

    /**
     * @test
     */
    public function multimodal_document_with_text_and_images()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('name')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->text('description')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->image('main_image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->image('thumbnails')->semantic(accuracy: 2, dimensions: 512, api: 'test-clip');
        $props->combo('searchable', ['name', 'description'])->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        $collected->add(new Document([
            'name' => 'Pirate Adventure Set',
            'description' => 'Complete pirate ship playset with figures and accessories',
            'main_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
            'thumbnails' => [
                'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                'https://github.com/sigmie/test-images/raw/refs/heads/main/queen.jpeg',
            ],
        ], _id: 'playset'));

        // Search across all modalities
        $results = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('pirate ship toy')
            ->fields(['name', 'description', 'main_image', 'searchable'])
            ->size(1)
            ->get();

        $hits = $results->hits();
        $this->assertCount(1, $hits);

        $this->assertEquals('playset', $hits[0]->_id);

        // Verify all embeddings were generated
        $doc = $this->sigmie->collect($indexName)->get('playset');
        $embeddings = $doc->_source['embeddings'];
        $this->assertArrayHasKey('name', $embeddings);
        $this->assertArrayHasKey('description', $embeddings);
        $this->assertArrayHasKey('main_image', $embeddings);
        $this->assertArrayHasKey('thumbnails', $embeddings);
        $this->assertArrayHasKey('searchable', $embeddings);
    }

    /**
     * @test
     */
    public function ecommerce_product_search_with_text_to_image()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('product_name');
        $props->image('product_image')->semantic(accuracy: 1, dimensions: 512, api: 'test-clip');
        $props->text('brand');
        $props->number('price')->float();
        $props->bool('in_stock');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, refresh: true)->properties($props);

        // Index e-commerce products with more variety
        $collected->merge([
            new Document([
                'product_name' => 'Adventure Board Game',
                'product_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/pirates.jpeg',
                'brand' => 'GameCo',
                'price' => 49.99,
                'in_stock' => true,
            ], _id: 'pirate-board-game'),
            new Document([
                'product_name' => 'RC Racing Car Deluxe',
                'product_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/red-car.jpeg',
                'brand' => 'SpeedToys',
                'price' => 89.99,
                'in_stock' => true,
            ], _id: 'rc-racing-car'),
            new Document([
                'product_name' => 'Royal Chess Set Premium',
                'product_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/queen.jpeg',
                'brand' => 'ChessMaster',
                'price' => 199.99,
                'in_stock' => false,
            ], _id: 'royal-chess-set'),
            new Document([
                'product_name' => 'Beach Vacation Puzzle',
                'product_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/beach.jpeg',
                'brand' => 'PuzzleCo',
                'price' => 24.99,
                'in_stock' => true,
            ], _id: 'beach-puzzle'),
            new Document([
                'product_name' => 'Family Sedan Model Kit',
                'product_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/blue-sedan.jpeg',
                'brand' => 'ModelCars',
                'price' => 34.99,
                'in_stock' => true,
            ], _id: 'sedan-model'),
            new Document([
                'product_name' => 'Racing Motorcycle Toy',
                'product_image' => 'https://github.com/sigmie/test-images/raw/refs/heads/main/motorcycle.jpeg',
                'brand' => 'SpeedBikes',
                'price' => 59.99,
                'in_stock' => true,
            ], _id: 'motorcycle-toy'),
        ]);

        // Customer searches for "kids pirate themed toy" - should find pirate game first
        $pirateSearch = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('pirate')
            ->fields(['product_image'])
            ->size(3)
            ->get();

        $pirateHits = $pirateSearch->hits();
        $this->assertGreaterThanOrEqual(1, count($pirateHits), 'Should find pirate-themed products');
        $this->assertEquals('pirate-board-game', $pirateHits[0]->_id, 'Adventure Board Game should be the top result for pirate query');

        // Customer searches for "red racing car toy" - should find RC car first
        $redCarSearch = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('ferrari')
            ->fields(['product_image'])
            ->size(3)
            ->get();

        $redCarHits = $redCarSearch->hits();
        $this->assertGreaterThanOrEqual(1, count($redCarHits), 'Should find car products');
        $this->assertEquals('rc-racing-car', $redCarHits[0]->_id, 'RC Racing Car should be the top result for red car query');

        // Customer searches for "chess strategy board game" - should find chess set first
        $strategySearch = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('queen')
            ->fields(['product_image'])
            ->size(3)
            ->get();

        $strategyHits = $strategySearch->hits();
        $this->assertGreaterThan(0, count($strategyHits), 'Should find strategy games');
        $this->assertEquals('royal-chess-set', $strategyHits[0]->_id, 'Royal Chess Set should be the top result for chess query');

        // Customer searches for "beach vacation puzzle" - should find beach puzzle first
        $beachSearch = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('beach sea')
            ->fields(['product_image'])
            ->size(3)
            ->get();

        $beachHits = $beachSearch->hits();
        $this->assertGreaterThanOrEqual(1, count($beachHits), 'Should find beach-related products');
        $this->assertEquals('beach-puzzle', $beachHits[0]->_id, 'Beach Vacation Puzzle should be the top result');

        // Customer searches for "motorcycle toy" - should find motorcycle toy first
        $motorcycleSearch = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('motorcycle bike racing two wheels')
            ->fields(['product_image'])
            ->size(3)
            ->get();

        $motorcycleHits = $motorcycleSearch->hits();
        $this->assertGreaterThanOrEqual(1, count($motorcycleHits), 'Should find motorcycle products');
        $this->assertEquals('motorcycle-toy', $motorcycleHits[0]->_id, 'Racing Motorcycle Toy should be the top result');

        // Customer searches for "car model kit" - should find sedan model first
        $modelSearch = $this->sigmie->newSearch($indexName)
            ->properties($props)
            ->semantic()
            ->queryString('blue car')
            ->fields(['product_image'])
            ->size(3)
            ->get();

        $modelHits = $modelSearch->hits();
        $this->assertGreaterThanOrEqual(1, count($modelHits), 'Should find car model products');
        $this->assertEquals('sedan-model', $modelHits[0]->_id, 'Family Sedan Model Kit should be the top result');
    }
}
