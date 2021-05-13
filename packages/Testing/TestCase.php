<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Contracts\Events;
use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Index;
use Symfony\Component\EventDispatcher\EventDispatcher;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use Testing, Events, AliasActions;

    public function setUp(): void
    {
        parent::setUp();

        $this->events = new EventDispatcher;

        $this->events->addListener('index.created', function (Index $index) {
            $this->createAlias($index->getName(), $this->testId());
        });

        $uses = $this->usedTraits();

        $this->setUpSigmieTesting($uses);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $uses = $this->usedTraits();

        $indices = $this->getIndices($this->testId());

        foreach ($indices as $index) {
            $index->delete();
        }

        $this->tearDownSigmieTesting($uses);
    }

    protected function testId(): string
    {
        $class = strtolower(static::class);
        $class = str_replace('\\', '_', $class);

        return  $class . '_' . $this->getName();
    }

    public function assertIndexExists(string $name)
    {
        $index = $this->getIndex($name);

        $this->assertInstanceOf(Index::class, $index);
    }

    private function classUsesTrait($class, $trait): bool
    {
        return in_array($trait, $this->usedTraits($class));
    }

    private function usedTraits()
    {
        $autoload = true;
        $class = $this;
        $traits = [];

        // Get traits of all parent classes
        do {
            $traits = array_merge(class_uses($class, $autoload), $traits);
        } while ($class = get_parent_class($class));

        // Get traits of all parent traits
        $traitsToSearch = $traits;
        while (!empty($traitsToSearch)) {
            $newTraits = class_uses(array_pop($traitsToSearch), $autoload);
            $traits = array_merge($newTraits, $traits);
            $traitsToSearch = array_merge($newTraits, $traitsToSearch);
        }

        foreach ($traits as $trait => $same) {
            $traits = array_merge(class_uses($trait, $autoload), $traits);
        }

        return array_unique($traits);
    }
}
