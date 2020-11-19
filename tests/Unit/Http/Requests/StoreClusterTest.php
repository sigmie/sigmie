<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\Cluster\StoreCluster;
use App\Rules\MultipleOf;
use PHPUnit\Framework\TestCase;

class StoreClusterTest extends TestCase
{
    /**
     * @var StoreCluster
     */
    private $request;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = new StoreCluster();
    }

    /**
     * @test
     */
    public function authorize_method_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    /**
     * @test
     */
    public function rules()
    {
        $colonRegex = '/:.*/';
        $expected = [
            'name' => ['alpha_num', 'required'],
            'nodes_count' => ['min:1', 'max:3', 'required'],
            'region_id' => ['required', 'integer'],
            'username' => ['required', 'alpha_num', 'not_regex:/:.*/'],
            'password' => ['required', 'min:4'],
            'memory' => ['required', new MultipleOf(256)],
            'cores' => ['required', new MultipleOf(2, [1])],
            'disk' => ['required', 'integer', 'min:10', 'max:30'],
            'project_id' => ['required']
        ];

        $this->assertEquals(1, preg_match($colonRegex, 'foo:bar'));
        $this->assertEquals(1, preg_match($colonRegex, 'f::o:bar'));
        $this->assertEquals(1, preg_match($colonRegex, ':foobar'));
        $this->assertEquals(0, preg_match($colonRegex, 'foobar'));
        $this->assertEquals(0, preg_match($colonRegex, 'usern/ex-dhs'));

        $this->assertEquals($expected, $this->request->rules());
    }
}
