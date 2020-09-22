<?php

declare(strict_types=1);

namespace App\Logging;

use Amp\Parallel\Worker\TaskFailureException;
use Monolog\Formatter\LogstashFormatter as MonologLogstashFormatter;
use Throwable;

class LogstashFormatter extends MonologLogstashFormatter
{
    protected function normalizeException(Throwable $e, int $depth = 0)
    {
        if ($e instanceof TaskFailureException) {

            $trace = [];

            foreach ($e->getOriginalTrace() as $frame) {
                if (isset($frame['file'])) {
                    $trace[] = $frame['file'] . ':' . $frame['line'];
                }
            }

            return [
                'class' => $e->getOriginalClassName(),
                'message' => $e->getOriginalMessage(),
                'code' => (int) $e->getOriginalCode(),
                'trace' => $trace,
                'file' => $e->getFile()
            ];
        }

        return parent::normalizeException($e, $depth);
    }
}
