<?php


namespace Sigma\Test\Unit;

use PHPUnit\Framework\TestCase;
use Sigma\Mapping\Types\Boolean;
use Sigma\Mapping\Types\Date;
use Sigma\Mapping\Types\IpAddress;
use Sigma\Mapping\Types\Long;

class TypesTest extends TestCase
{
    /**
     * @test
     */
    public function long(): void
    {
        $type = new Long();

        $this->assertEquals('long', $type->field());
    }

    /**
     * @test
     */
    public function ipAddress(): void
    {
        $type = new IpAddress();

        $this->assertEquals('ip', $type->field());
    }

    /**
     * @test
     */
    public function boolean(): void
    {
        $type = new Boolean();

        $this->assertEquals('boolean', $type->field());
    }

    /**
     * @test
     */
    public function date(): void
    {
        $type = new Date();

        $this->assertEquals('date', $type->field());
    }
}
