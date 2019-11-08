<?php

namespace Sigma\Test\Unit\Index\Responses;

use Closure;
use PHPUnit\Framework\TestCase;
use Sigma\Index\Response\Insert;
use Sigma\Index\Response\Remove;

class RemoveTest extends TestCase
{
    private $response;

    public function setUp(): void
    {
        $this->response = new Remove();
    }

    /**
     * @test
     */
    public function result(): void
    {
        $result = $this->response->result(['acknowledged' => true], function () {
        });

        $this->assertTrue($result);
    }
}
