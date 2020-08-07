<?php

declare(strict_types=1);

namespace Sigmie\Tests\Integration;

use Sigmie\Search\FailedOperation;
use Sigmie\Search\Indices\Index;
use Sigmie\Search\Indices\Service as IndicesService;
use Sigmie\Search\SuccessOperation;
use Sigmie\Tests\Helpers\IntegrationTestCase;

class IndicesServiceTest extends IntegrationTestCase
{
    /**
     * @var IndicesService
     */
    private $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = new IndicesService($this->client());
    }

    /**
     * @test
     */
    public function get_index_instance()
    {
        $index = $this->service->create('bar');

        $this->assertInstanceOf(Index::class, $index);
    }

    /**
     * @test
     */
    public function get_index_failed_operation()
    {
        $index = $this->service->get('baz');

        $this->assertInstanceOf(FailedOperation::class, $index);
    }

    /**
     * @test
     */
    public function create_index()
    {
        $index = $this->service->create('bar');

        $this->assertInstanceOf(Index::class, $index);
        $this->assertEquals($index->getName(), 'bar');
    }

    /**
     * @test
     */
    public function delete_index_success_operation()
    {
        $index = $this->service->create('bar');

        $result = $this->service->delete($index->getName());

        $this->assertInstanceOf(SuccessOperation::class, $result);
    }

    /**
     * @test
     */
    public function list_gets_listed_index()
    {
        $this->service->create('foo');
        $this->service->create('bar');
        $this->service->create('baz');

        $this->assertCount(3, $this->service->list());
    }

    /**
     * @test
     */
    public function delete_index_failed_operation()
    {
        $result = $this->service->delete('no-existed-index');

        $this->assertInstanceOf(FailedOperation::class, $result);
    }
}
