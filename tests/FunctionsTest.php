<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use Sigmie\Testing\TestCase;
use Sigmie\Testing\Assert;
use Sigmie\Index\AliasedIndex;
use Sigmie\Index\Analysis\Analyzer;
use Sigmie\Index\Analysis\CharFilter\HTMLStrip;
use Sigmie\Index\Analysis\Tokenizers\Pattern;
use Sigmie\Index\Analysis\Tokenizers\Whitespace;
use Sigmie\Index\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Index\APIs\Index;
use Sigmie\Index\Mappings;
use Sigmie\Mappings\Blueprint;
use Sigmie\Mappings\DynamicMappings;
use Sigmie\Mappings\Properties;
use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Text;

use function Sigmie\Functions\random_letters;

class FunctionsTest extends TestCase
{
    /**
     * @test
     */
    public function random_letters_test()
    {
        $letters = random_letters(16);

        $this->assertNotEquals($letters, random_letters(16));
        $this->assertIsString($letters);
        $this->assertEquals(16, strlen($letters));
    }
}
