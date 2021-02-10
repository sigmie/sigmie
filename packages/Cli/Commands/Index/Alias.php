<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Base\APIs\Calls\Alias as AliasAPI;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Index;
use Sigmie\Cli\BaseCommand;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Alias extends BaseCommand
{

    use IndexActions, AliasActions, AliasAPI;

    protected static $defaultName = 'index:alias';

    protected function configure()
    {
        parent::configure();

        $this->addArgument('action', InputOption::VALUE_REQUIRED, 'Action to perform. (add, remove)');
        $this->addArgument('index', InputOption::VALUE_REQUIRED, 'Index name');
        $this->addArgument('alias', InputOption::VALUE_REQUIRED, 'Alias');
        $this->addOption('from', '-o', InputOption::VALUE_OPTIONAL, 'from');
        $this->addOption('to', '-n', InputOption::VALUE_OPTIONAL, 'to');
    }

    public function executeCommand(): int
    {
        $action = $this->input->getArgument('action');
        $index = $this->input->getArgument('index');
        $alias = $this->input->getArgument('alias');

        $index = $this->getIndex($index);

        if ($action === 'add') {

            $index->setAlias($alias);

            $this->output->writeln("Alias {$alias} added to index {$index->getName()}.");

            return 0;
        }

        if ($action === 'remove') {

            $index->removeAlias($alias);

            $this->output->writeln("Alias removed from index {$index->getName()}.");

            return 0;
        }


        if ($action === 'switch') {

            $body = ['actions' => [
                ['remove' => ['index' => $index, 'alias' => $alias]],
                ['add' => ['index' => $index, 'alias' => $alias]]
            ]];

            $this->aliasAPICall('POST', $body);

            $this->output->writeln("Alias moved from index {$index->getName()} to {$alias}.");

            return 0;
        }

        return 1;
    }
}
