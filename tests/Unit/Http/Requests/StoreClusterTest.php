<?php

namespace Tests\Unit;

use App\Http\Requests\StoreCluster;
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
            'data_center' => ['required'],
            'username' => ['required', "not_regex:{$colonRegex}"],
            'password' => ['required', 'min:4', 'max:8'],
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
