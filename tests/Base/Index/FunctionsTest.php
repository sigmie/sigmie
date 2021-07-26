<?php

declare(strict_types=1);

namespace Sigmie\Tests\Base\Index;

use Sigmie\Base\Analysis\Analyzer;
use Sigmie\Base\Analysis\CharFilter\HTMLStrip;
use Sigmie\Base\Analysis\Tokenizers\Pattern;
use Sigmie\Base\Analysis\Tokenizers\Whitespace;
use Sigmie\Base\Analysis\Tokenizers\WordBoundaries;
use Sigmie\Base\APIs\Index;
use Sigmie\Base\Contracts\ConfigurableTokenizer;
use Sigmie\Base\Index\Blueprint;
use Sigmie\Base\Index\DynamicMappings;
use Sigmie\Base\Index\Mappings;
use Sigmie\Base\Index\Settings;
use Sigmie\Base\Mappings\Types\Boolean;
use Sigmie\Base\Mappings\Types\Date;
use Sigmie\Base\Mappings\Types\Number;
use Sigmie\Base\Mappings\Types\Text;
use Sigmie\Support\Alias\Actions;
use Sigmie\Testing\TestCase;

use function Sigmie\Helpers\random_letters;

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
