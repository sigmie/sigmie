<?php

declare(strict_types=1);

namespace Sigmie\Cli;

use Sigmie\Cli\Contracts\OutputFormat;
use Sigmie\Cli\Outputs\ClientInfo;
use Sigmie\Http\JsonClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected InputInterface $input;

    protected OutputInterface $output;

    protected JsonClient $client;

    abstract public function executeCommand(): int;

    protected function configure()
    {
        $this->addArgument('es_url');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        $url = $this->input->getArgument('es_url');

        $this->client = JsonClient::createWithoutAuth($url);

        $this->renderInfo();

        return $this->executeCommand();
    }

    protected function output(OutputFormat $outputFormat)
    {
        $outputFormat->output($this->output);
    }

    private function renderInfo()
    {
        [$url, $port] = explode(':', $this->input->getArgument('es_url'));

        $info = new ClientInfo($url, $port, '7.8.0');
        $info->output($this->output);
    }
}
