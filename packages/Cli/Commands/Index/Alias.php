<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Base\APIs\Alias as AliasAPI;
use Sigmie\Cli\BaseCommand;
use Sigmie\Support\Alias\Actions;
use Sigmie\Support\Alias\Actions as IndexActions;
use Symfony\Component\Console\Input\InputOption;

class Alias extends BaseCommand
{

    use IndexActions, Actions, AliasAPI;

    protected static $defaultName = 'index:alias';

    public function executeCommand(): int
    {
        $action = $this->input->getArgument('action');
        $index = $this->input->getArgument('index');
        $alias = $this->input->getArgument('alias');

        $index = $this->getIndex($index);

        if ($action === 'add') {

            $index->setAlias($alias);

            $this->output->writeln("Alias {$alias} added to index {$index->name()}.");

            return 0;
        }

        if ($action === 'remove') {

            $index->removeAlias($alias);

            $this->output->writeln("Alias removed from index {$index->name()}.");

            return 0;
        }

        return 1;
    }

    protected function configure()
    {
        parent::configure();

        $this->addArgument('action', InputOption::VALUE_REQUIRED, 'Action to perform. (add, remove)');
        $this->addArgument('index', InputOption::VALUE_REQUIRED, 'Index name');
        $this->addArgument('alias', InputOption::VALUE_REQUIRED, 'Alias');
    }
}
