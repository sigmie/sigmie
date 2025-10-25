<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\AI\NewJsonSchema;
use Sigmie\AI\Prompt;
use Sigmie\Testing\TestCase;

class PromptTest extends TestCase
{
    /**
     * @test
     */
    public function json_schema(): void
    {
        $prompt = new Prompt;

        $prompt->answerJsonSchema(function (NewJsonSchema $schema): void {
            $schema->name('catalog');
            $schema->array('products', function (NewJsonSchema $items): void {
                $items->string('name');
                $items->number('price');
                $items->object('manufacturer', function (NewJsonSchema $obj): void {
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
    public function default_json_schema(): void
    {
        $prompt = new Prompt;

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
    public function opeanai_response(): void
    {
        $prompt = new Prompt;

        $prompt->user('populate the catalog with 1 product');
        $prompt->answerJsonSchema(function (NewJsonSchema $schema): void {
            $schema->name('catalog');
            $schema->array('products', function (NewJsonSchema $items): void {
                $items->string('name');
                $items->number('price');
                $items->object('manufacturer', function (NewJsonSchema $obj): void {
                    $obj->string('company');
                    $obj->string('country');
                });
            });
        });

        $llm = $this->llmApi;
        $answer = $llm->jsonAnswer($prompt);
        $json = $answer->json();

        // Lenient assertions for small models like tinyllama
        $this->assertIsArray($json);
        $this->assertNotEmpty($json);
    }
}
