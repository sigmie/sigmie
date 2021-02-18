<?php

declare(strict_types=1);

namespace Sigmie\Testing\Laravel;

trait Traits
{
    abstract protected function testId(): string;

    protected function setUpSigmieTraits(array $uses)
    {
        if (isset($uses[ClearIndices::class])) {
            $this->clearIndices();
            $this->beforeApplicationDestroyed(fn () => $this->clearIndices());
        }
    }
}
