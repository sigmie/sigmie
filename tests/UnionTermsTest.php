<?php

declare(strict_types=1);

namespace Sigmie\Tests;

use PHPUnit\Framework\TestCase;
use Sigmie\Query\Aggregations\Metrics\Sum;
use Sigmie\Query\Aggs;

class UnionTermsTest extends TestCase
{
    /** @test */
    public function it_keeps_declared_fields_in_params_and_uses_fixed_script_source(): void
    {
        $fields = ['champion_country', "runner_up_country']; return params.injected; //"];
        $aggs = new Aggs;

        $aggs->unionTerms('countries', $fields)
            ->size(5)
            ->order('metric', 'desc')
            ->aggregate(fn (Aggs $sub): Sum => $sub->sum('metric', 'prize'));

        $raw = $aggs->toRaw()['countries'];
        $script = $raw['terms']['script'];

        $this->assertSame($fields, $script['params']['fields']);
        $this->assertStringContainsString('params.fields', $script['source']);
        $this->assertStringNotContainsString('champion_country', $script['source']);
        $this->assertStringNotContainsString('params.injected', $script['source']);
        $this->assertSame(5, $raw['terms']['size']);
        $this->assertSame(['metric' => 'desc'], $raw['terms']['order']);
        $this->assertSame('prize', $raw['aggs']['metric']['sum']['field']);
    }
}
