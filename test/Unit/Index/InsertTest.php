<?php


namespace Sigma\Test\Unit\Index;

use PHPUnit\Framework\TestCase;
use Sigma\Contract\Subscribable;
use Sigma\Index\Action\Insert;

class InsertTest  extends TestCase
{
    /**
     * @test
     */
    public function subscribable(): void
    {
        $insertAction = new Insert();

        $this->assertInstanceOf(Subscribable::class, $insertAction);
    }
}
