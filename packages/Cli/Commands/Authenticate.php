<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands;

use Sigmie\Cli\BaseCommand;
use Symfony\Component\Console\Input\InputArgument;

class Authenticate extends BaseCommand
{
    protected static $defaultName = 'auth';

    protected function configure()
    {
        $this->addArgument('cluster', InputArgument::OPTIONAL, 'Cluster connection', 'default');
    }

    protected function executeCommand(): int
    {
        return 0;
    }
}
