<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Sigmie\AI\Prompt;

class NewRagPrompt extends Prompt
{
    protected array $contextFields = [];
    protected string $conversationId = '';
    protected string $userToken = '';
    protected string $instructions = '';
    protected string $summary = '';
    protected array $tags = [];
    protected array $turns = [];

    public function __construct(
        protected array $hits,
        protected array $messages = []
    ) {
        parent::__construct($messages);
    }

    public function contextFields(array $contextFields): self
    {
        $this->contextFields = $contextFields;

        $context = $this->createContext($this->contextFields);

        $this->system('Context: ' . json_encode($context));

        return $this;
    }

    public function hits(): array
    {
        return $this->hits;
    }

    private function createContext($fields)
    {
        $context = [];

        foreach ($this->hits as $hit) {
            $contextItem = [];
            foreach ($fields as $field) {
                $contextItem[$field] = is_array($hit)
                    ? ($hit['_source'][$field] ?? null)
                    : dot($hit->_source)->get($field);
            }
            $context[] = $contextItem;
        }

        return $context;
    }
}
