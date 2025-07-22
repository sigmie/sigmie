<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Exception;
use RachidLaasri\Travel\Travel;
use Sigmie\Document\Document;
use Sigmie\Languages\English\Builder as EnglishBuilder;
use Sigmie\Languages\English\English;
use Sigmie\Languages\German\Builder as GermanBuilder;
use Sigmie\Languages\German\German;
use Sigmie\Languages\Greek\Builder as GreekBuilder;
use Sigmie\Languages\Greek\Greek;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\CharFilter\Mapping;
use Sigmie\Index\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\NewAnalyzer;
use Sigmie\Mappings\NewProperties;
use Sigmie\Query\Queries\MatchAll;
use Sigmie\Sigmie;
use Sigmie\Testing\Assert;
use Sigmie\Testing\TestCase;

class IndexTest extends TestCase
{
    /**
     * @test
     */
    public function raw()
    {
        $alias = uniqid();

        $this->sigmie->newIndex($alias)
            ->dontTokenize()
            ->create();

        $raw = $this->sigmie->index($alias)->raw;

        $this->assertNotNull($raw['settings']['index']['uuid'] ?? null);
    }
}
