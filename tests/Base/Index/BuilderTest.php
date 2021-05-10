<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use PHPUnit\Framework\TestCase;
use Sigmie\Base\Analysis\Tokenizers\Whitespaces;
use Sigmie\Base\Index\Builder as NewIndex;

class BuilderTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @test
     */
    public function foo(): void
    {
        $builder = (new NewIndex('docs'))
            ->withPrefix('sigmie')
            // ->language(new English)
            ->withDefaultStopwords()
            ->withoutMappings()
            ->tokenizeOn(new Whitespaces)
            ->mappings(function ($blueprint) {

                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('search')->completion();
                $blueprint->text('keywords')->keyword();
                $blueprint->text('content')->unstructuredText($analyzer);

                $blueprint->number('adults')->integer();
                $blueprint->number('price')->float();

                $blueprint->date('created_at');
                $blueprint->bool('bar');
            })
            ->stopwords(['if', 'we', 'ours'])
            ->synonyms([
                ['i-pad', 'ipad', 'ipad'], // two-way
                ['goog' => 'google'] // one-way
            ])
            ->stemming([
                'mice' => 'mouse',
                'skies' => 'sky'
            ])
            ->keywords(['skies'])
            ->create();
    }
}
