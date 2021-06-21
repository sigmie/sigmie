<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\Support\Alias\Actions;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\Mappings;
use Sigmie\Testing\TestCase;

class MappingsTest extends TestCase
{
    use Index, Actions;

    /**
     * @test
     */
    public function analyzers_collection()
    {
        $blueprint = new Blueprint;
        $defaultAnalyzer = new Analyzer('foo', new WordBoundaries());
        $analyzer = new Analyzer('bar', new WordBoundaries());

        $blueprint->text('title')->searchAsYouType();
        $blueprint->text('content')->unstructuredText($analyzer);
        $blueprint->number('adults')->integer();
        $blueprint->number('price')->float();
        $blueprint->date('created_at');
        $blueprint->bool('is_valid');

        $properties = $blueprint($defaultAnalyzer);
        $mappings = new Mappings($defaultAnalyzer, $properties);

        $analyzers = $mappings->analyzers()->toArray();

        $this->assertContains($defaultAnalyzer, $analyzers);
        $this->assertContains($analyzer, $analyzers);
    }
}
