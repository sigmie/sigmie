<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Alias;

use Sigmie\Base\APIs\Calls\Alias as AliasAPI;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Index;
use Sigmie\Cli\BaseCommand;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SwitchAlias extends BaseCommand
{
    use IndexActions, AliasActions, AliasAPI;

    protected static $defaultName = 'alias:switch';

    protected function configure()
    {
        parent::configure();

        $this->addArgument('alias', InputOption::VALUE_REQUIRED, 'Alias');
        $this->addOption('from', null, InputOption::VALUE_OPTIONAL, 'from');
        $this->addOption('to', null, InputOption::VALUE_OPTIONAL, 'to');
    }

    public function executeCommand(): int
    {
        $alias = $this->input->getArgument('alias');
        $fromIndex = $this->input->getOption('from');
        $toIndex = $this->input->getOption('to');

        $this->switchAlias($alias, new Index($fromIndex), new Index($toIndex));

        $from = '<fg=green>' . $fromIndex . '</>';
        $to = '<fg=green>' . $toIndex . '</>';
        $alias = '<fg=green>' . $alias . '</>';

        $this->output->writeln("Alias {$alias} moved from index {$from} to index {$to}.");

        return 0;
    }
}
