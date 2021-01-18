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

    abstract public function executeCommand(): int;

    protected function configure()
    {
        $this->addArgument('es_url');
    }

    private function createConnection()
    {
        $home = getenv('HOME');
        $homePath = "{$home}/.sigmie";
        $filePath = "{$homePath}/auth.json";
        $content = file_get_contents($filePath);
        $json  = json_decode($content, true);

        $key = $json['default'];
        return new Connection(JsonClient::create($key));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        $this->setHttpConnection($this->createConnection());

        $this->renderInfo();

        return $this->executeCommand();
    }

    protected function output(OutputFormat $outputFormat)
    {
        $outputFormat->output($this->output);
    }

    private function renderInfo()
    {
        $home = getenv('HOME');
        $homePath = "{$home}/.sigmie";
        $filePath = "{$homePath}/auth.json";
        $content = file_get_contents($filePath);
        $json  = json_decode($content, true);

        $key = $json['default'];
        [$url, $port] = explode(':', $key);

        $info = new ClientInfo($url, $port, '7.8.0');
        $info->output($this->output);
    }
}
