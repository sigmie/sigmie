<?php

namespace Sigma\Test\Unit;

use PHPUnit\Framework\TestCase;
use Sigma\Element;

class ElementTest extends TestCase
{
    /**
     * @test
     */
    public function setIdentifier(): void
    {
        $element = $this->getMockForAbstractClass(Element::class);
        $element->setIdentifier('foo');

        $this->assertEquals('foo', $element->getIdentifier());
    }
}
