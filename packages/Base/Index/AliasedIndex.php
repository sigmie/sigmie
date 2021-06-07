<?php

declare(strict_types=1);

namespace Sigmie\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\DefaultAnalyzer;
use Sigmie\Base\Index\Index;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;

class AliasedIndex extends Index
{
    public function __construct(
        string $identifier,
        string $alias,
        array $aliases,
        ?Settings $settings = null,
        ?Mappings $mappings = null,
    ) {
        parent::__construct($identifier, $aliases, $settings, $mappings);
    }

    public function update(callable $update): Index
    {
        $analyzer = $update($this->defaultAnalyzer());

        return $this;
    }

    protected function defaultAnalyzer(): Analyzer
    {
        return $this->settings->analysis->defaultAnalyzer();
    }
}
