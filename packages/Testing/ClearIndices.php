<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;

trait ClearIndices
{
    use IndexActions;

    private ?array $existingIndices = null;

    private function settingUp(): bool
    {
        return $this->existingIndices === null;
    }

    public function clearIndices()
    {
        $indices = $this->listIndices()->map(fn (Index $index) => $index->getName())->toArray();

        if ($this->settingUp()) {
            $this->existingIndices = $indices;
            return;
        }

        foreach ($this->listIndices() as $index) {
            if (in_array($index->getName(), $this->existingIndices)) {
                continue;
            }

            $this->deleteIndex($index->getName());
        }
    }
}
