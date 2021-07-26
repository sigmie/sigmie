<?php

declare(strict_types=1);

namespace Sigmie\Support;

use Amp\Parallel\Worker\Environment;
use Amp\Parallel\Worker\Task;

final class BulkBody implements Task
{
    public function __construct(private array $docs)
    {
    }

    public function run(Environment $environment)
    {
        $body = [];

        array_walk($this->docs, function ($document) use (&$body) {
            $body = [
                ...$body,
                ['create' => ($document->getId() !== null) ? ['_id' => $document->getId()] : (object) []],
                $document->attributes(),
            ];
        });

        return $body;
    }
}
