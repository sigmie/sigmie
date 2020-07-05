<?php

namespace App\Logging;

use Monolog\Logger;
use Google\Cloud\Logging\LoggingClient;
use Monolog\Handler\PsrHandler;

class CreateStackdriverLogger
{
    public function __invoke(array $config): Logger
    {
        $logger = LoggingClient::psrBatchLogger('app');
        $handler = new PsrHandler($logger);

        return new Logger('stackdriver', [$handler]);
    }
}
