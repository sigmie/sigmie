<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use RachidLaasri\Travel\Travel;
use Sigmie\Document\AliveCollection;
use Sigmie\Document\Document;
use Sigmie\Mappings\Blueprint;
use Sigmie\Index\NewIndex;
use Sigmie\Testing\TestCase;
use Exception;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\CharFilter\Mapping;
use Sigmie\Index\Analysis\CharFilter\Pattern as PatternCharFilter;
use Sigmie\Index\Analysis\Tokenizers\NonLetter;
use Sigmie\Index\Analysis\Tokenizers\Pattern as PatternTokenizer;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\English\Builder as EnglishBuilder;
use Sigmie\English\English;
use Sigmie\German\Builder as GermanBuilder;
use Sigmie\German\German;
use Sigmie\Greek\Builder as GreekBuilder;
use Sigmie\Greek\Greek;
use Sigmie\Testing\Assert;

class SearchTest extends TestCase
{
    /**
     * @test
     */
    public function foo()
    {
        $alias = uniqid();

        // $search = $this->sigmie->search('products')->filter('category:disney')->get();
        $search = $this->sigmie->template('products')
            ->fields(['username'])
            ->query('demo')
            ->filterable()
            ->get()->source();

        ray($search);
    }
}
