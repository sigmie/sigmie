<?php

declare(strict_types=1);

namespace App\Logging;

use Google\Cloud\Logging\LoggingClient;
use Monolog\Handler\PsrHandler;
use Monolog\Logger;

class CreateStackdriverLogger
{
    /**
     * Initialize app engine stack driver logger
     */
    public function __invoke(array $config): Logger
    {
        $logger = LoggingClient::psrBatchLogger('app');

        $handler = new PsrHandler($logger);

        return new Logger('stackdriver', [$handler]);
    }
}
