<?php

declare(strict_types=1);

namespace Sigmie\Cli\Commands;

use Sigmie\Http\JsonClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Authenticate extends Command
{
    protected static $defaultName = 'auth';

    protected function configure()
    {
        $this->addArgument('host', InputArgument::OPTIONAL, 'Elasticsearch host', '127.0.0.1');
        $this->addArgument('username', InputArgument::OPTIONAL, 'Elasticsearch username', null);
        $this->addArgument('password', InputArgument::OPTIONAL, 'Elasticsearch password', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $host = $input->getArgument('host');
        $username = $input->getArgument('username');
        $password = $input->getArgument('username');

        $home = getenv('HOME');
        $homePath = "{$home}/.sigmie";
        $filePath = "{$homePath}/auth.json";

        if (is_dir($homePath) === false) {
            mkdir($homePath);
            file_put_contents($filePath, json_encode(['auth' => null], JSON_PRETTY_PRINT));
        }

        if (file_exists($filePath) === false) {
            touch($filePath);
        }

        $content = file_get_contents($filePath);
        $json  = json_decode($content, true);

        if (is_null($json['default'])) {
            $json['default'] = $host;
        }

        $json['default'] = $host;
        $json[$host] = ['username' => $username, 'password' => $password];

        file_put_contents($filePath, json_encode($json, JSON_PRETTY_PRINT));

        return 0;
    }
}
