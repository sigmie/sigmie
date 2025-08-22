<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Mappings\NewProperties;
use Sigmie\Testing\TestCase;

class EmbeddingsTest extends TestCase
{
    /**
     * @test
     */
    public function embeddings_mapping()
    {
        $indexName = uniqid();

        $blueprint = new NewProperties;
        $blueprint->text('title')->semantic(accuracy: 1, dimensions: 128);
        $blueprint->nested('comments', function (NewProperties $props) {
            $props->text('text')->semantic(accuracy: 1, dimensions: 128);
            $props->object('user', function (NewProperties $props) {
                $props->text('name')
                    ->semantic(accuracy: 1, dimensions: 128)
                    ->semantic(accuracy: 7, dimensions: 256);
            });
        });

        $this->sigmie->newIndex($indexName)->properties($blueprint)->create();

        $index = $this->sigmie->index($indexName)->raw;

        $this->assertArrayHasKey('embeddings', $index['mappings']['properties']);
        $this->assertArrayHasKey('title', $index['mappings']['properties']['embeddings']['properties']);
        $this->assertArrayHasKey('comments', $index['mappings']['properties']['embeddings']['properties']);
        $this->assertArrayHasKey('user', $index['mappings']['properties']['embeddings']['properties']['comments']['properties']);
        $this->assertArrayHasKey('name', $index['mappings']['properties']['embeddings']['properties']['comments']['properties']['user']['properties']);
        $this->assertArrayHasKey('text', $index['mappings']['properties']['embeddings']['properties']['comments']['properties']);

        $name = $index['mappings']['properties']['embeddings']['properties']['title']['properties'];
        $text = $index['mappings']['properties']['embeddings']['properties']['comments']['properties']['text']['properties'];
        $title = $index['mappings']['properties']['embeddings']['properties']['title']['properties'];

        $this->assertIsArray($name);
        $this->assertIsArray($text);
        $this->assertIsArray($title);

        $this->assertEquals(128, $name[array_key_first($name)]['dims']);
        $this->assertEquals(128, $text[array_key_first($text)]['dims']);
        $this->assertEquals(128, $title[array_key_first($title)]['dims']);
    }
}
