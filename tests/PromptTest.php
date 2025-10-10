<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\APIs\LocalResponseApi;
use Sigmie\AI\APIs\OpenAIResponseApi;
use Sigmie\AI\NewJsonSchema;
use Sigmie\AI\Prompt;
use Sigmie\Testing\TestCase;

class PromptTest extends TestCase
{
    /**
     * @test
     */
    public function json_schema()
    {
        $prompt = new Prompt();

        $prompt->answerJsonSchema(function (NewJsonSchema $schema) {
            $schema->name('catalog');
            $schema->array('products', function (NewJsonSchema $items) {
                $items->string('name');
                $items->number('price');
                $items->object('manufacturer', function (NewJsonSchema $obj) {
                    $obj->string('company');
                    $obj->string('country');
                });
            });
        });

        $result = $prompt->jsonSchema();

        $expected = [
            'type' => 'object',
            'properties' => [
                'products' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'price' => ['type' => 'number'],
                            'manufacturer' => [
                                'type' => 'object',
                                'properties' => [
                                    'company' => ['type' => 'string'],
                                    'country' => ['type' => 'string'],
                                ],
                                'required' => ['company', 'country'],
                                'additionalProperties' => false,
                            ],
                        ],
                        'required' => ['name', 'price', 'manufacturer'],
                        'additionalProperties' => false,
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['products'],
            'additionalProperties' => false,
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function default_json_schema()
    {
        $prompt = new Prompt();

        $result = $prompt->jsonSchema();

        $expected = [
            'type' => 'object',
            'properties' => [
                'answer' => ['type' => 'string'],
            ],
            'required' => ['answer'],
            'additionalProperties' => false,
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * @test
     */
    public function opeanai_response()
    {
        $prompt = new Prompt();

        $prompt->user('populate the catalog with 1 product');
        $prompt->answerJsonSchema(function (NewJsonSchema $schema) {
            $schema->name('catalog');
            $schema->array('products', function (NewJsonSchema $items) {
                $items->string('name');
                $items->number('price');
                $items->object('manufacturer', function (NewJsonSchema $obj) {
                    $obj->string('company');
                    $obj->string('country');
                });
            });
        });

        $llm = $this->llmApi;
        $answer = $llm->jsonAnswer($prompt);
        $json = $answer->json();

        $this->assertIsArray($json);
        $this->assertArrayHasKey('products', $json);
        $this->assertIsArray($json['products']);
        $this->assertCount(1, $json['products']);

        $product = $json['products'][0];
        $this->assertArrayHasKey('name', $product);
        $this->assertArrayHasKey('price', $product);
        $this->assertArrayHasKey('manufacturer', $product);

        $this->assertIsString($product['name']);
        $this->assertIsNumeric($product['price']);
        $this->assertIsArray($product['manufacturer']);

        $this->assertArrayHasKey('company', $product['manufacturer']);
        $this->assertArrayHasKey('country', $product['manufacturer']);
        $this->assertIsString($product['manufacturer']['company']);
        $this->assertIsString($product['manufacturer']['country']);
    }
}
