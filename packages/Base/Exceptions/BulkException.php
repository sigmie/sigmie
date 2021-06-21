<?php

declare(strict_types=1);

namespace Sigmie\Base\Exceptions;

use Exception;
use Sigmie\Support\Contracts\Collection;

class BulkException extends Exception
{
    protected Collection $failedActions;

    public function __construct(Collection $collection)
    {
        $this->failedActions = $collection;

        $text = $this->createText();

        parent::__construct($text, 200);
    }

    public function getFailedActions(): Collection
    {
        $this->failedActions;
    }

    private function createText()
    {
        return implode(',', $this->failedActions->map(function ($vals) {
            [$action, $values] = $vals;
            $id = $values['_id'];
            $reason = $values['error']['reason'];

            return "Action {$action} for Document with id {$id} failed. Reason: {$reason}";
        })->toArray());
    }
}
