<?php

declare(strict_types=1);

namespace Sigmie\Testing;

use Sigmie\Base\Index\AliasActions;
use Sigmie\Base\Index\Index;
use Sigmie\Sigmie;

class TestCase extends \PHPUnit\Framework\TestCase
{
    use Testing, AliasActions, Assertions;

    protected Sigmie $sigmie;

    public function setUp(): void
    {
        parent::setUp();

        $uses = $this->usedTraits();

        $this->setUpSigmieTesting($uses);

        $this->sigmie = new Sigmie($this->httpConnection);
    }

    public function tearDown(): void
    {
        parent::tearDown();

        $uses = $this->usedTraits();

        $this->tearDownSigmieTesting($uses);
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
