<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Support\Alias\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Sigmie\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class Create extends BaseCommand
{
    use IndexActions;

    protected static $defaultName = 'index:create';

    public function executeCommand(): int
    {
        $name = $this->input->getArgument('name');
        $alias = $this->input->getOption('alias');

        $index = new Index($name);

        $this->createIndex($index);

        if (is_null($alias) === false) {
            $index->setAlias($alias);

            $index->removeAlias();
        }

        $this->output->writeln("Index {$name} created.");

        return 1;
    }

    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Index name');

        $this->addOption('alias', 'a', InputOption::VALUE_OPTIONAL, 'Index alias');

        parent::configure();
    }
}
