<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\Actions as IndexActions;

trait ClearIndices
{
    use IndexActions;

    public function clearIndices()
    {
        foreach ($this->listIndices() as $index) {
            if (str_starts_with($index->name(), $this->testId())) {
                $this->deleteIndex($index->getName());
            }
        }
    }

    abstract protected function testId(): string;
}
