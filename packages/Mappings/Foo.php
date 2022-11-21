<?php

declare(strict_types=1);

namespace Sigmie\Mappings;

use Sigmie\Mappings\Types\Boolean;
use Sigmie\Mappings\Types\Date;
use Sigmie\Mappings\Types\Keyword;
use Sigmie\Mappings\Types\Nested;
use Sigmie\Mappings\Types\Number;
use Sigmie\Mappings\Types\Text;
use Sigmie\Mappings\Types\Type;
use Sigmie\Shared\Collection;

interface Foo
{
    public function name();

    public function title();

    public function email();

    public function summary();

    public function description();

    public function subtitle();

    public function price();

    public function rating();

    public function active();

    public function word();

    public function sentence();

    public function text();

    public function path();

    public function address();

    public function city();

    public function category();

    public function number();

    public function phone();

    public function html();

    public function code();
}
