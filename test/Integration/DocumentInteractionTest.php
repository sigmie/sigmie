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
     * @var Index
     */
    private $index;

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

        $this->index = new Index('foo');

        $this->sigma->insert($this->index);
    }

    /**
     * @test
     */
    public function add(): void
    {
        $document = new Document();

        $this->index->add($document);

        $this->assertInstanceOf(Document::class, $document);
    }

    /**
     * @test
     */
    public function remove(): void
    {
        $document = new Document();

        $this->index->add($document);

        $result = $this->index->remove($document);

        $this->assertTrue($result);
    }
}
