<?php declare(strict_types=1);

namespace Sigmie\Cli\Commands\Mapping;

use Sigmie\Base\APIs\Calls\Mget;
use Sigmie\Base\APIs\Calls\Search;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\DocumentsTable;
use Symfony\Component\Console\Input\InputArgument;

class ListMapping extends BaseCommand
{
    use Mget, IndexActions, Search;

    protected static $defaultName = 'map:list';

    protected Index $index;

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

    protected function index(): Index
    {
        return $this->index;
    }
}
