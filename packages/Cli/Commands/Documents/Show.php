<?php declare(strict_types=1);

namespace Sigmie\Cli\Commands\Documents;

use Sigmie\Base\APIs\Mget;
use Sigmie\Base\Index\Index;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\DocumentTable;
use Sigmie\Support\Alias\Actions as IndexActions;
use Symfony\Component\Console\Input\InputArgument;

class Show extends BaseCommand
{
    use Mget, IndexActions;

    protected static $defaultName = 'doc:show';

    protected Index $index;

    public function executeCommand(): int
    {
        $indexName = $this->input->getArgument('index');
        $documentId = $this->input->getArgument('document');

        $this->index = $this->getIndex($indexName);

        $response = $this->mgetAPICall(['docs' => [['_id' => $documentId]]]);

        $table = new DocumentTable($response->json());

        $table->output($this->output);

        return 1;
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('index', InputArgument::REQUIRED, 'Index name');
        $this->addArgument('document', InputArgument::REQUIRED, 'Document id');
    }

    protected function index(): Index
    {
        return $this->index;
    }
}
