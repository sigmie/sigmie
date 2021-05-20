<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Base\APIs\Calls\Cat;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\IndexListTable;

class ListIndices extends BaseCommand
{
    use Cat;

    protected static $defaultName = 'index:list';

    public function executeCommand(): int
    {
        $catIndexResponse = $this->catAPICall('/indices', 'GET');
        $catAliasResponse = $this->catAPICall('/aliases', 'GET');

        $table = new IndexListTable(
            $catIndexResponse->json(),
            $catAliasResponse->json()
        );

        $table->output($this->output);

        return 1;
    }

    protected function configure()
    {
        parent::configure();
    }
}
