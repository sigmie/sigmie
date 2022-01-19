<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Documents;

use Sigmie\Base\Actions\Alias as IndexActions;
use Sigmie\Base\APIs\Mget;
use Sigmie\Base\APIs\Search;
use Sigmie\Base\Index\AbstractIndex;
use Sigmie\Base\Search\Query;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\DocumentsTable;
use Symfony\Component\Console\Input\InputArgument;

class ListDocs extends BaseCommand
{
    use Mget;
    use IndexActions;
    use Search;

    protected static $defaultName = 'doc:list';

    protected AbstractIndex $index;

    public function executeCommand(): int
    {
        $indexName = $this->input->getArgument('index');

        $this->index = $this->getIndex($indexName);

        $query = new Query(['match_all' => (object) []]);
        $query->index($this->index());
        $query->setFrom(0)->setSize(10000);

        $response = $this->searchAPICall($query);

        $raw = $this->input->getOption('raw');

        if ($raw) {
            $this->handleRaw($response);

            return 0;
        }

        $data = $response->json('hits')['hits'];

        $table = new DocumentsTable($data, $this->index()->name());

        $table->output($this->output);

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
