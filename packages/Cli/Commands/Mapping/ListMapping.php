<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Mapping;

use Sigmie\Base\Actions\Alias as IndexActions;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\DocumentsTable;
use Symfony\Component\Console\Input\InputArgument;

class ListMapping extends BaseCommand
{
    use Mget;
    use IndexActions;
    use Search;

    protected static $defaultName = 'map:list';

    protected AbstractIndex $index;

    public function executeCommand(): int
    {
        $indexName = $this->input->getArgument('index');

        // $table = new DocumentsTable($data, $this->index()->getName());

        // $table->output($this->output);

        return 1;
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('index', InputArgument::REQUIRED, 'Index name');
    }

    protected function index(): AbstractIndex
    {
        return $this->index;
    }
}
