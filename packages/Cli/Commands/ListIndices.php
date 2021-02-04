<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands;

use Sigmie\Base\APIs\Calls\Cat;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\IndexListTable;

class ListIndices extends BaseCommand
{
    use Cat;

    protected static $defaultName = 'index:list';

    public function executeCommand(): int
    {
        $catResponse = $this->catAPICall('/indices', 'GET');

        $table = new IndexListTable($catResponse->json());

        $table->output($this->output);

        return 1;
    }

    protected function configure()
    {
        parent::configure();
    }
}
