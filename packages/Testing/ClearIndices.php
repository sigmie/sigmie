<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;

trait ClearIndices
{
    use IndexActions;

    abstract protected function testId(): string;

    public function clearIndices()
    {
        foreach ($this->listIndices() as $index) {
            if (str_starts_with($index->getName(), $this->testId())) {
                $this->deleteIndex($index->getName());
            }

            continue;
        }
    }
}
