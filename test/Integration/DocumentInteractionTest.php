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

        $this->sigma->clear(false);
    }

    /**
     * @test
     */
    public function add(): void
    {
        $index = new Index('foo');

        $this->sigma->insert($index);

        $this->sigma->bootElement($index);

        $document = new Document();

        $document = $index->add($document);

        $this->assertInstanceOf(Document::class, $document);
    }
}
