<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\AI\Contracts\LLMApi;
use Sigmie\AI\Contracts\LLMAnswer;
use Sigmie\AI\Prompt;
use Sigmie\Rag\LLMJsonAnswer;
use PHPUnit\Framework\Assert;

class FakeLLMApi implements LLMApi
{
    protected array $answerCalls = [];

    protected array $streamAnswerCalls = [];

    protected array $jsonAnswerCalls = [];

    public function __construct(
        protected LLMApi $realApi
    ) {}

    public function answer(Prompt $prompt): LLMAnswer
    {
        $this->answerCalls[] = [
            'prompt' => $prompt,
            'messages' => $prompt->messages(),
        ];

        return $this->realApi->answer($prompt);
    }

    public function streamAnswer(Prompt $prompt): iterable
    {
        $this->streamAnswerCalls[] = [
            'prompt' => $prompt,
            'messages' => $prompt->messages(),
        ];

        return $this->realApi->streamAnswer($prompt);
    }

    public function jsonAnswer(Prompt $prompt): LLMJsonAnswer
    {
        $this->jsonAnswerCalls[] = [
            'prompt' => $prompt,
            'messages' => $prompt->messages(),
            'schema' => $prompt->jsonSchema(),
        ];

        return $this->realApi->jsonAnswer($prompt);
    }

    public function model(): string
    {
        return $this->realApi->model();
    }

    public function assertAnswerWasCalled(int $times = null): void
    {
        $actualCount = count($this->answerCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'answer() was never called');
            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('answer() was called %d times, expected %d times', $actualCount, $times));
    }

    public function assertStreamAnswerWasCalled(int $times = null): void
    {
        $actualCount = count($this->streamAnswerCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'streamAnswer() was never called');
            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('streamAnswer() was called %d times, expected %d times', $actualCount, $times));
    }

    public function assertJsonAnswerWasCalled(int $times = null): void
    {
        $actualCount = count($this->jsonAnswerCalls);

        if ($times === null) {
            Assert::assertGreaterThan(0, $actualCount, 'jsonAnswer() was never called');
            return;
        }

        Assert::assertEquals($times, $actualCount, sprintf('jsonAnswer() was called %d times, expected %d times', $actualCount, $times));
    }

    public function assertAnswerWasCalledWithMessage(string $role, string $contentSubstring): void
    {
        foreach ($this->answerCalls as $call) {
            foreach ($call['messages'] as $message) {
                if ($message['role']->value === $role && str_contains($message['content'], $contentSubstring)) {
                    Assert::assertTrue(true);
                    return;
                }
            }
        }

        Assert::fail(sprintf('answer() was never called with %s message containing: "%s"', $role, $contentSubstring));
    }

    public function getAnswerCalls(): array
    {
        return $this->answerCalls;
    }

    public function getStreamAnswerCalls(): array
    {
        return $this->streamAnswerCalls;
    }

    public function getJsonAnswerCalls(): array
    {
        return $this->jsonAnswerCalls;
    }

    public function reset(): void
    {
        $this->answerCalls = [];
        $this->streamAnswerCalls = [];
        $this->jsonAnswerCalls = [];
    }
}
