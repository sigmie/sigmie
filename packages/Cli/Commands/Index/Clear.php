<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands\Index;

use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Index\Actions as IndexActions;
use Sigmie\Cli\BaseCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class Clear extends BaseCommand
{
    use Cat, Index;

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
            $this->clearIndices();

            $this->output->writeln('Indices cleared.');
        } else {
            $this->output->writeln('Abort.');
        }

        return 1;
    }

    protected function clearIndices()
    {
        $response = $this->catAPICall('/indices', 'GET',);

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }
    }

    protected function configure()
    {
        parent::configure();
    }
}
