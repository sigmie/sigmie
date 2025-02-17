<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Sigmie\Document\Document;
use Sigmie\Mappings\Field;
use Sigmie\Mappings\NewProperties;
use Sigmie\Search\Autocomplete\HttpProcessor;
use Sigmie\Search\Autocomplete\NewPipeline;
use Sigmie\Search\Autocomplete\Processor;
use Sigmie\Testing\TestCase;

class SemanticTest extends TestCase
{
    /**
     * @test
     */
    public function semantic_search()
    {
        $pipeline = new NewPipeline(
            $this->sigmie->getElasticsearchConnection(),
            'embedding-pipeline'
        );

        $pipeline->addPocessor(new HttpProcessor());
        $pipeline->create();

        $indexName = uniqid();

        $blueprint = new NewProperties();
        $blueprint->denseVector('embedding');
        // $blueprint->type(new Field(
        //     name: 'embedding',
        //     type: 'elastiknn_dense_float_vector',
        //     options: [
        //         'elastiknn' => [
        //             "dims" => 384,
        //             "model" => "exact",
        //         ]
        //     ]
        // ));

        $index = $this->sigmie
            ->newIndex($indexName)
            ->properties($blueprint)
            ->create();

        $index = $this->sigmie->collect($indexName, refresh: true);

        $index->merge([
            new Document([
                'name' => 'King',
                'embedding' => $this->embeddings('King')
            ]),
            new Document([
                'name' => 'Queen',
                'embedding' => $this->embeddings('Queen')
            ]),
        ]);

        $response = $this->sigmie
            // ->newSemanticSearch($indexName)
            ->newSearch($indexName)
            ->noResultsOnEmptySearch()
            ->properties($blueprint)
            // ->embeddings($this->embeddings('man'))
            ->embeddings($this->embeddings('woman'))
            ->get();

        $hits = $response->json('hits.hits');
    }

    public function embeddings(string $text)
    {
        $client = new Client();

        $request = new Request(
            "POST",
            "https://app.sigmie.com/embeddings",
            [
                "Content-Type" => "application/json; charset=utf-8",
            ],
            json_encode([
                'text' => $text
            ])
        );

        $response = $client->send($request);

        $json = json_decode($response->getBody()->getContents(), true);

        return $json;
    }
}
