<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands;

use Sigmie\Cli\BaseCommand;
use Sigmie\Base\Index\Actions as IndexActions;

class ListIndex extends BaseCommand
{
    use IndexActions;

    protected static $defaultName = 'index:list';

    public function executeCommand(): int
    {
        $res = $this->listIndices();

        foreach ($res as $index) {
            dump($index->getName());
        }

        return 1;
    }

    protected function configure()
    {
        parent::configure();
    }
}
