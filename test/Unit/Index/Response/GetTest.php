<?php

namespace Sigma\Test\Unit\Index\Responses;

use Sigma\Index\Response\Get;
use PHPUnit\Framework\TestCase;
use Sigma\Index\Index;

class GetTest extends TestCase
{
    private $response;

    public function setUp(): void
    {
        $this->response = new Get();
    }

    /**
     * @test
     */
    public function result(): void
    {
        /** @var  Index $result */
        $result = $this->response->result(['identifier' => ['foo', 'bar']]);

        $this->assertInstanceOf(Index::class, $result);
        $this->assertEquals('identifier', $result->getIdentifier());
    }
}
