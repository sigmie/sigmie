<?php


namespace Sigma\Test\Unit\Index;

use PHPUnit\Framework\TestCase;
use Sigma\Contract\Subscribable;
use Sigma\Index\Action\Create;

class CreateTest  extends TestCase
{
    /**
     * @test
     */
    public function subscribable(): void
    {
        $createAction = new Create();

        $this->assertInstanceOf(Subscribable::class, $createAction);
    }
}
