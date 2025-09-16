<?php

declare(strict_types=1);

namespace Sigmie\Search;

class NewRagPrompt
{
    protected array $guardrails = [];

    protected array $contextFields = [];

    protected string $question = '';

    protected string $context = '';

    protected string $template = '
        Question:
        {{question}}

        Guardrails:
        {{guardrails}}


        Context:
        {{context}}';

    public function __construct(protected array $hits) {}

    public function template(string $template): self {

        $this->template = $template;

        return $this;
    }

    public function contextFields(array $contextFields): self {

        $this->contextFields = $contextFields;

        return $this;
    }

    public function question(string $question): self {

        $this->question = $question;

        return $this;
    }

    public function guardrails(array $guardrails): self
    {

        $this->guardrails = $guardrails;

        return $this;
    }

    public function create(): string {

        $context = array_map(function($hit) {
            $filteredSource = [];
            foreach ($this->contextFields as $field) {
                if (isset($hit->_source[$field])) {
                    $filteredSource[$field] = $hit->_source[$field];
                }
            }
            return json_encode($filteredSource);
        }, $this->hits);
        $template = $this->template;

        $template = str_replace('{{question}}', $this->question, $template);
        $template = str_replace('{{context}}', implode("\n", $context), $template);
        $template = str_replace('{{guardrails}}', implode("\n", $this->guardrails), $template);
        $template = str_replace('{{hits}}', implode("\n", $context), $template);

        return $template;
    }
}
