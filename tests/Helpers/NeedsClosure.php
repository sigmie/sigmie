<?php

declare(strict_types=1);

namespace Tests\Helpers;

use Closure;
use PHPUnit\Framework\MockObject\MockObject;

trait NeedsClosure
{
    /**
     * @var Closure|MockObject
     */
    private $closureMock;

    /**
     * @var MockObject
     *
     * @method null closure()
     */
    private $callableMock;

    public function closure()
    {
        $this->callableMock = $this->getMockBuilder(\stdClass::class)->addMethods(['closure'])->getMock();

        $this->closureMock = fn (...$args) => $this->callableMock->closure(...$args);
    }

    public function expectClosureCalledWith(...$args)
    {
        $this->callableMock->expects($this->once())
            ->method('closure')
            ->with(...$args);
    }
}
