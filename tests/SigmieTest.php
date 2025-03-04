<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Mappings\Types\Text;
use Sigmie\Plugins\Elastiknn\NearestNeighbors as ElastiknnNearestNeighbors;
use Sigmie\Query\Queries\NearestNeighbors;
use Sigmie\Semantic\Embeddings\SigmieAI;
use Sigmie\Sigmie;
use Sigmie\Testing\TestCase;

class SigmieTest extends TestCase
{
    /**
     * @test
     */
    public function elastiknn_plugin_is_registered()
    {
        Sigmie::registerPlugins([
            'elastiknn'
        ]);

        $queries = (new SigmieAI)->queries('test', 'test query string', new Text('test'));

        $this->assertInstanceOf(ElastiknnNearestNeighbors::class, $queries[0]);
    }

    /**
     * @test
     */
    public function dense_vector_type_is_registered()
    {
        Sigmie::registerPlugins([
            // 'elastiknn'
        ]);

        $queries = (new SigmieAI)->queries('test', 'test query string', new Text('test'));

        $this->assertInstanceOf(NearestNeighbors::class, $queries[0]);
    }

    /**
     * @test
     */
    public function with_application_prefix()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie->application($application)
            ->newIndex($alias)
            ->create();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndexExists("{$application}-{$alias}");
        $this->assertIndexNotExists("{$alias}");
    }

    /**
     * @test
     */
    public function without_application_prefix()
    {
        $alias = uniqid();

        $application = uniqid();

        $this->sigmie
            ->newIndex($alias)
            ->create();

        $this->sigmie->newIndex($alias)
            ->decimalDigit('decimal_digit_filter')
            ->create();

        $this->assertIndexNotExists("{$application}-{$alias}");
        $this->assertIndexExists("{$alias}");
    }
}
