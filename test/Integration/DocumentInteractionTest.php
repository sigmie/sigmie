<?php

namespace Sigma\Test\Integration;

use Elasticsearch\ClientBuilder;
use Sigma\Sigma;
use Sigma\Collection;
use Sigma\Index\Index;
use PHPUnit\Framework\TestCase;
use Sigma\Document\Document;

class DocumentInteractionTest extends TestCase
{
    /**
     * Client instance
     *
     * @var Sigma
     */
    private $sigma;

    /**
     * Setup stubs
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return void
     */
    public function setup(): void
    {
        $host = getenv('ES_HOST');
        $builder = ClientBuilder::create();
        $elasticsearch = $builder->setHosts([$host])->build();
        $this->sigma = Sigma::create($elasticsearch);

        $indices = $this->sigma->elasticsearch()->cat()->indices(['index' => '*']);

        foreach ($indices as $index) {
            $this->sigma->elasticsearch()->indices()->delete(['index' => $index['index']]);
        }
    }

    /**
    * @test
    */
    public function foo(): void
    {

    }
}
