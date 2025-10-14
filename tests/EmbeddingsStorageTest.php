<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Document\Document;
use Sigmie\Mappings\NewProperties;
use Sigmie\Support\VectorMath;
use Sigmie\Testing\TestCase;

class EmbeddingsStorageTest extends TestCase
{
    /**
     * @test
     */
    public function embeddings_are_stored_and_retrieved_correctly_per_field()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('description')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
            $props->text('author')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Test Title',
                'description' => 'Test Description',
                'content' => 'Test Content',
                'comments' => [
                    ['text' => 'Comment 1', 'author' => 'Author 1'],
                    ['text' => 'Comment 2', 'author' => 'Author 2'],
                ],
            ], _id: 'test-doc'),
        ]);

        // Retrieve the document
        $doc = $collected->get('test-doc');

        // Verify embeddings structure exists
        $this->assertArrayHasKey('embeddings', $doc->_source);

        // Verify each field has embeddings
        $embeddings = $doc->_source['embeddings'];

        // Title field (384 dims)
        $this->assertArrayHasKey('title', $embeddings);
        $titleEmbedding = $embeddings['title'];
        $this->assertIsArray($titleEmbedding);
        $titleKey = array_key_first($titleEmbedding);
        $this->assertStringContainsString('dims384', $titleKey);
        $this->assertCount(384, $titleEmbedding[$titleKey]);

        // Description field (384 dims)
        $this->assertArrayHasKey('description', $embeddings);
        $descEmbedding = $embeddings['description'];
        $this->assertIsArray($descEmbedding);
        $descKey = array_key_first($descEmbedding);
        $this->assertStringContainsString('dims384', $descKey);
        $this->assertCount(384, $descEmbedding[$descKey]);

        // Content field (384 dims)
        $this->assertArrayHasKey('content', $embeddings);
        $contentEmbedding = $embeddings['content'];
        $this->assertIsArray($contentEmbedding);
        $contentKey = array_key_first($contentEmbedding);
        $this->assertStringContainsString('dims384', $contentKey);
        $this->assertCount(384, $contentEmbedding[$contentKey]);

        // Comments.text field (384 dims, concatenated)
        $this->assertArrayHasKey('comments', $embeddings);
        $this->assertArrayHasKey('text', $embeddings['comments']);
        $commentTextEmbedding = $embeddings['comments']['text'];
        $this->assertIsArray($commentTextEmbedding);
        $commentTextKey = array_key_first($commentTextEmbedding);
        $this->assertStringContainsString('dims384', $commentTextKey);
        $this->assertCount(384, $commentTextEmbedding[$commentTextKey]);

        // Comments.author field (384 dims, concatenated)
        $this->assertArrayHasKey('author', $embeddings['comments']);
        $commentAuthorEmbedding = $embeddings['comments']['author'];
        $this->assertIsArray($commentAuthorEmbedding);
        $commentAuthorKey = array_key_first($commentAuthorEmbedding);
        $this->assertStringContainsString('dims384', $commentAuthorKey);
        $this->assertCount(384, $commentAuthorEmbedding[$commentAuthorKey]);
    }

    /**
     * @test
     */
    public function embeddings_use_md5_keys_for_field_identification()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Test Title',
            ], _id: 'test-doc'),
        ]);

        // Retrieve the document
        $doc = $collected->get('test-doc');

        // Verify embeddings key uses MD5
        $embeddings = $doc->_source['embeddings']['title'];
        $key = array_key_first($embeddings);

        // Key format should be: m{accuracy}_efc{efConstruction}_dims{dimensions}_{similarity}_{strategy}
        // e.g., m16_efc80_dims256_cosine_concat
        $this->assertMatchesRegularExpression('/^m\d+_efc\d+_dims\d+_\w+_\w+$/', $key);

        // Verify the key is consistent (same input should produce same key)
        $collected->merge([
            new Document([
                'title' => 'Test Title',
            ], _id: 'test-doc-2'),
        ]);

        $doc2 = $collected->get('test-doc-2');
        $embeddings2 = $doc2->_source['embeddings']['title'];
        $key2 = array_key_first($embeddings2);

        $this->assertEquals($key, $key2, 'Keys should be consistent for same configuration');
    }

    /**
     * @test
     */
    public function stored_embeddings_are_normalized()
    {
        $indexName = uniqid();


        $props = new NewProperties;
        $props->text('title')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');
        $props->text('content')->semantic(accuracy: 1, dimensions: 384, api: 'test-embeddings');

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)->properties($props);

        $collected->merge([
            new Document([
                'title' => 'Test Title for Normalization',
                'content' => 'This is test content to verify that embeddings are properly normalized when stored.',
            ], _id: 'norm-test'),
        ]);

        // Retrieve the document
        $doc = $collected->get('norm-test');

        // Check title embedding (256 dims)
        $titleEmbeddings = $doc->_source['embeddings']['title'];
        $titleKey = array_key_first($titleEmbeddings);
        $titleVector = $titleEmbeddings[$titleKey];

        $this->assertTrue(
            VectorMath::isNormalized($titleVector),
            'Title embedding vector should be normalized (magnitude ≈ 1.0)'
        );

        // Check content embedding (512 dims)
        $contentEmbeddings = $doc->_source['embeddings']['content'];
        $contentKey = array_key_first($contentEmbeddings);
        $contentVector = $contentEmbeddings[$contentKey];

        $this->assertTrue(
            VectorMath::isNormalized($contentVector),
            'Content embedding vector should be normalized (magnitude ≈ 1.0)'
        );

        // Verify magnitude is actually close to 1.0
        $titleMagnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $titleVector)));
        $contentMagnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $contentVector)));

        $this->assertEqualsWithDelta(1.0, $titleMagnitude, 0.01, 'Title vector magnitude should be ~1.0');
        $this->assertEqualsWithDelta(1.0, $contentMagnitude, 0.01, 'Content vector magnitude should be ~1.0');
    }

    /**
     * @test
     */
    public function average_strategy_produces_normalized_vectors_in_nested_fields()
    {
        $indexName = uniqid();

        $props = new NewProperties;
        // Use Average strategy for nested comments field (multiple items will be averaged)
        $props->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 2, dimensions: 384, api: 'test-embeddings'); // accuracy 2 uses Average strategy
        });

        $this->sigmie->newIndex($indexName)->properties($props)->create();

        $collected = $this->sigmie->collect($indexName, true)
        ->populateEmbeddings()
        ->properties($props);

        // Create document with multiple comments (will trigger averaging)
        $collected->merge([
            new Document([
                'comments' => [
                    ['text' => 'First comment about the product'],
                    ['text' => 'Second comment with different opinion'],
                    ['text' => 'Third comment adding more context'],
                ],
            ], _id: 'avg-test'),
        ]);

        // Retrieve the document
        $doc = $collected->get('avg-test');

        // Check that the averaged embedding is normalized
        $commentEmbeddings = $doc->_source['embeddings']['comments']['text'];
        $commentKey = array_key_first($commentEmbeddings);
        $commentVector = $commentEmbeddings[$commentKey];

        // Verify the vector is normalized after averaging
        $this->assertTrue(
            VectorMath::isNormalized($commentVector),
            'Averaged comment embedding should be normalized'
        );

        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $commentVector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.01,
            'Averaged vector magnitude should be ~1.0, got: ' . $magnitude);
    }
}
