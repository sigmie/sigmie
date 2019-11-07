<?php

namespace Sigma\Test\Unit\Index\Responses;

use PHPUnit\Framework\TestCase;
use Sigma\Index\Response\Insert;

class InsertTest extends TestCase
{
    private $response;

    public function setUp(): void
    {
        $this->response = new Insert();
    }

    /**
     * @test
     */
    public function result(): void
    {
        $result = $this->response->result([
            'acknowledged' => true,
            'index' => 'bar'
        ],function () { });

        $this->assertEquals($result->name, 'bar');
    }
}
