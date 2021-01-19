<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\Index;
use Sigmie\Cli\BaseCommand;
use Sigmie\Cli\Commands\ListIndices;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\StringInput;

class Create extends BaseCommand
{
    use IndexActions;

    protected static $defaultName = 'index:create';

    public function executeCommand(): int
    {
        $name = $this->input->getArgument('name');

        $this->createIndex(new Index($name));

        $this->output->writeln("Index {$name} created.");

        return 1;
    }

    protected function configure()
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Index name');

        parent::configure();
    }
}
