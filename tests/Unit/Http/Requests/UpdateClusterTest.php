<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Cluster\UpdateCluster;
use App\Rules\MultipleOf;
use PHPUnit\Framework\TestCase;

class UpdateClusterTest extends TestCase
{
    /**
     * @var UpdateCluster
     */
    private $request;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = new UpdateCluster();
    }

    /**
     * @test
     */
    public function authorize_is_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    /**
     * @test
     */
    public function rules()
    {
        $colonRegex = '/:.*/';
        $this->assertEquals([
            'nodes_count' => ['min:1', 'integer', 'max:3', 'required'],
            'region_id' => ['required', 'integer'],
            'username' => ['required', 'alpha_num', 'not_regex:' . $colonRegex],
            'password' => ['required', 'min:4', 'max:8'],
            'memory' => ['required', new MultipleOf(256)],
            'cores' => ['required', new MultipleOf(2, [1])],
            'disk' => ['required', 'integer', 'min:10', 'max:10000'],
        ], $this->request->rules());

        $this->assertEquals(1, preg_match($colonRegex, 'foo:bar'));
        $this->assertEquals(1, preg_match($colonRegex, 'f::o:bar'));
        $this->assertEquals(1, preg_match($colonRegex, ':foobar'));
        $this->assertEquals(0, preg_match($colonRegex, 'foobar'));
        $this->assertEquals(0, preg_match($colonRegex, 'usern/ex-dhs'));
    }
}
