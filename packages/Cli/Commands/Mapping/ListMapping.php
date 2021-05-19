<?php

namespace Sigmie\Cli\Commands\Mapping;

use Sigmie\Base\APIs\Calls\Cat;
use Sigmie\Base\APIs\Calls\Mget;
use Sigmie\Base\APIs\Calls\Search;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\IndexListTable;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Search\Query;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Cli\Outputs\DocumentsTable;
use Sigmie\Cli\Outputs\DocumentTable;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class ListMapping extends BaseCommand
{
    use Mget, IndexActions, Search;

    protected static $defaultName = 'map:list';

    protected Index $index;

    protected function configure()
    {
        parent::configure();

        $this->addArgument('index', InputArgument::REQUIRED, 'Index name');
    }

    protected function index(): Index
    {
        return $this->index;
    }

    public function executeCommand(): int
    {
        $indexName = $this->input->getArgument('index');

        // $table = new DocumentsTable($data, $this->index()->getName());

        // $table->output($this->output);

        return 1;
    }
}
