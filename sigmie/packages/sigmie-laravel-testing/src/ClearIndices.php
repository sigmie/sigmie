<?php

declare(strict_types=1);

namespace Sigmie\Testing\Laravel;

trait ClearIndices
{
    abstract protected function testId(): string;

    public function clearIndices()
    {
        /** @var  TestingHelper */
        $helper = $this->app->make(TestingHelper::class);
        $helper->setTestId($this->testId());

        $helper->clearIndices();
    }
}
