<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use InvalidArgumentException;
use ParaTest\Runners\PHPUnit\BaseRunner;
use ParaTest\Runners\PHPUnit\Worker\WrapperWorker;
use PHPUnit\TextUI\TestRunner;
use Sigmie\Base\APIs\Cat;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\API;
use Sigmie\Base\Http\Connection;
use Sigmie\Http\JSONClient;

class ParallelRunner extends BaseRunner
{
    use Cat, Index, API;

    /** @var WrapperWorker[] */
    private $workers = [];

    public function clearIndices(string $host)
    {
        $client = JSONClient::create($host);

        $this->setHttpConnection(new Connection($client));

        $response = $this->catAPICall('/indices', 'GET',);

        $names = array_map(fn ($data) => $data['index'], $response->json());

        $nameChunks = array_chunk($names, 50);

        foreach ($nameChunks as $chunk) {
            $this->indexAPICall(implode(',', $chunk), 'DELETE');
        }
    }

    protected function beforeLoadChecks(): void
    {
        if ($this->options->functional()) {
            throw new InvalidArgumentException(
                'The `functional` option is not supported yet in the WrapperRunner. Only full classes can be run due ' .
                    'to the current PHPUnit commands causing classloading issues.'
            );
        }
    }

    protected function doRun(): void
    {
        $this->startWorkers();
        $this->assignAllPendingTests();
        $this->sendStopMessages();
        $this->waitForAllToFinish();
    }

    private function startWorkers(): void
    {
        for ($token = 1; $token <= $this->options->processes(); ++$token) {
            $this->clearIndices("es_testing_{$token}:9200");
            $this->workers[$token] = new WrapperWorker($this->output, $this->options, $token);
            $this->workers[$token]->start();
        }
    }

    private function assignAllPendingTests(): void
    {
        $phpunit        = $this->options->phpunit();
        $phpunitOptions = $this->options->filtered();

        while (count($this->pending) > 0 && count($this->workers) > 0) {
            foreach ($this->workers as $worker) {
                if (!$worker->isRunning()) {
                    throw $worker->getWorkerCrashedException();
                }

                if (!$worker->isFree()) {
                    continue;
                }

                $this->flushWorker($worker);
                if ($this->exitcode > 0 && $this->options->stopOnFailure()) {
                    $this->pending = [];
                } elseif (($pending = array_shift($this->pending)) !== null) {
                    $worker->assign($pending, $phpunit, $phpunitOptions, $this->options);
                }
            }

            usleep(self::CYCLE_SLEEP);
        }
    }

    private function flushWorker(WrapperWorker $worker): void
    {
        $reader = $worker->printFeedback($this->printer);

        if ($this->hasCoverage()) {
            $coverageMerger = $this->getCoverage();
            assert($coverageMerger !== null);
            if (($coverageFileName = $worker->getCoverageFileName()) !== null) {
                $coverageMerger->addCoverageFromFile($coverageFileName);
            }
        }

        $worker->reset();

        if ($reader === null) {
            return;
        }

        $exitCode = TestRunner::SUCCESS_EXIT;
        if ($reader->getTotalErrors() > 0) {
            $exitCode = TestRunner::EXCEPTION_EXIT;
        } elseif ($reader->getTotalFailures() > 0 || $reader->getTotalWarnings() > 0) {
            $exitCode = TestRunner::FAILURE_EXIT;
        }

        $this->exitcode = max($this->exitcode, $exitCode);
    }

    private function sendStopMessages(): void
    {
        foreach ($this->workers as $worker) {
            $worker->stop();
        }
    }

    private function waitForAllToFinish(): void
    {
        while (count($this->workers) > 0) {
            foreach ($this->workers as $index => $worker) {
                if ($worker->isRunning()) {
                    continue;
                }

                if (!$worker->isFree()) {
                    throw $worker->getWorkerCrashedException();
                }

                $this->flushWorker($worker);
                unset($this->workers[$index]);
            }

            usleep(self::CYCLE_SLEEP);
        }
    }
}
