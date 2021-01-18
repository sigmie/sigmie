<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Outputs\IndexListTable;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Symfony\Component\Console\Input\InputArgument;

class Create extends BaseCommand
{
    use IndexActions;
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'index:create';

    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Index name');

        parent::configure();
    }

    public function executeCommand(): int
    {
        $name = $this->input->getArgument('name');

        $this->createIndex(new Index($name));

        $this->output->writeln("Index {$name} was created");

        return 1;
    }
}
