<?php

declare(strict_types=1);

namespace Sigmie\Cli;

use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Cli\Contracts\OutputFormat;
use Sigmie\Cli\Outputs\ClientInfo;
use Sigmie\Http\JsonClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    use API;

    protected InputInterface $input;

    protected OutputInterface $output;

    protected JsonClient $client;

    protected Config $config;

    abstract protected function executeCommand(): int;

    protected function configure()
    {
        parent::configure();

        $this->config = new Config;

        $this->setHttpConnection($this->createHttpConnection());
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        $this->renderInfo();

        return $this->executeCommand();
    }

    protected function output(OutputFormat $outputFormat)
    {
        $outputFormat->output($this->output);
    }

    private function createHttpConnection()
    {
        $cluster = $this->config->getActiveCluster();

        return new Connection(JsonClient::create($cluster['host'] . ':' . $cluster['port']));
    }

    private function renderInfo()
    {
        $cluster = $this->config->getActiveCluster();

        $info = new ClientInfo($cluster['host'], $cluster['port'], '7.8.0');
        $info->output($this->output);
    }
}
