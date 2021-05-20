<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Cli\BaseCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Clear extends BaseCommand
{
    use IndexActions;

    protected static $defaultName = 'index:clear';

    public function executeCommand(): int
    {
        /** @var QuestionHelper */
        $helper = $this->getHelper('question');

        $res = $helper->ask(
            $this->input,
            $this->output,
            new ConfirmationQuestion('Are you sure you want to delete all indices ?', false)
        );

        if ($res) {
            foreach ($this->listIndices() as $index) {
                $this->deleteIndex($index->getName());
            }

            $this->output->writeln('Indices cleared.');
        } else {
            $this->output->writeln('Abort.');
        }

        return 1;
    }

    protected function configure()
    {
        parent::configure();
    }
}
