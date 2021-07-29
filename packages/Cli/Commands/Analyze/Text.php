<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Analyze;

use Sigmie\Base\APIs\Alias as AliasAPI;
use Sigmie\Base\APIs\Analyze;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\AnalyzeTable;
use Sigmie\Support\Alias\Actions;
use Sigmie\Support\Alias\Actions as IndexActions;
use Symfony\Component\Console\Input\InputOption;

class Text extends BaseCommand
{
    use IndexActions, Actions, AliasAPI, Analyze;

    protected static $defaultName = 'analyze:text';

    public function executeCommand(): int
    {
        $index = $this->input->getArgument('index');
        $text = $this->input->getArgument('text');
        $analyzer = $this->input->getArgument('analyzer');

        $res = $this->analyzeAPICall($index, $text, $analyzer);

        $table = new AnalyzeTable($res->json());

        $table->output($this->output);

        return 1;
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('index', InputOption::VALUE_REQUIRED, 'Index name');
        $this->addArgument('text', InputOption::VALUE_REQUIRED, 'Text to be analyzed');
        $this->addArgument('analyzer', InputOption::VALUE_REQUIRED, 'Analyzer');
    }
}
