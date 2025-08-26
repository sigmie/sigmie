<?php

declare(strict_types=1);

namespace Sigmie\Search;

class NewPrompt
{
    protected string $systemPrompt = '';
    protected string $template = '';

    public function system(string $prompt): self
    {
        $this->systemPrompt = $prompt;

        return $this;
    }

    public function template(string $template): self
    {
        $this->template = $template;

        return $this;
    }

    public function getSystemPrompt(): string
    {
        return $this->systemPrompt;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }
}
