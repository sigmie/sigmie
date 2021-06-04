<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\CharFilter\PatternFilter;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\APIs\Calls\Index;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Sigmie;
use Sigmie\Testing\ClearIndices;
use Sigmie\Testing\TestCase;

class UpdateTest extends TestCase
{
    use Index, ClearIndices, AliasActions;

    /**
     * @var Sigmie
     */
    private $sigmie;

    public function setUp(): void
    {
        parent::setUp();

        $this->sigmie = new Sigmie($this->httpConnection, $this->events);
    }

    /**
     * @test
     */
    public function foo()
    {
        $this->sigmie->newIndex('foo')
            ->normalizer(new PatternFilter('/.*/', 'bar'))
            ->tokenizeOn(new Pattern('/[ ]/'))
            ->stemming([
                ['foo' => 'bar']
            ])
            ->mappings(function (Blueprint $blueprint) {
                $blueprint->bool('foo');
                $blueprint->date('from');
                $blueprint->number('price')->float();
                $blueprint->number('count')->integer();

                $blueprint->text('title')->searchAsYouType();
                $blueprint->text('description')->unstructuredText();

                return $blueprint;
            })
            ->create();

        $this->sigmie->index('foo');
    }

    private function indexData(string $name): array
    {
        $json = $this->indexAPICall($name, 'GET')->json();
        $indexName = array_key_first($json);
        return $json[$indexName];
    }
}
