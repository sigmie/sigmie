<?php

declare(strict_types=1);

namespace Sigmie\Search;

use Closure;

class NewPrompt
{
    protected string $question = '';
    protected string $instructions = '';
    protected string $template = '';
    protected ?NewContextComposer $contextComposer = null;
    
    public function question(string $question): self
    {
        $this->question = $question;
        return $this;
    }
    
    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;
        return $this;
    }
    
    public function context(Closure $callback): self
    {
        $this->contextComposer = new NewContextComposer();
        $callback($this->contextComposer);
        return $this;
    }
    
    public function template(string $template): self
    {
        $this->template = $template;
        return $this;
    }
    
    public function getQuestion(): string
    {
        return $this->question;
    }
    
    public function getInstructions(): string
    {
        return $this->instructions;
    }
    
    public function getTemplate(): string
    {
        return $this->template;
    }
    
    public function getContextComposer(): ?NewContextComposer
    {
        return $this->contextComposer;
    }
    
    // Legacy support
    public function system(string $prompt): self
    {
        return $this->instructions($prompt);
    }
    
    public function getSystemPrompt(): string
    {
        return $this->getInstructions();
    }
}
