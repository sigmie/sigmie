<?php

declare(strict_types=1);

namespace Sigmie\Rag;

use DateTime;
use Sigmie\Document\Document;
use Sigmie\Mappings\Types\Date;

abstract class LLMAnswer
{
    public readonly string $timestamp;

    // TODO make readonly
    public string $conversationId;

    public function __construct(
        public readonly string $model,
        protected array $request,
        protected array $response,
    ) {
        $this->timestamp = (new DateTime('now'))->format('Y-m-d\TH:i:s.uP');
    }

    public function model(): string
    {
        return $this->model;
    }

    public function conversation(string $conversationId)
    {
        $this->conversationId = $conversationId;
    }

    abstract public function __toString(): string;
}
