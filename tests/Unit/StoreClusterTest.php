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
        $expected = [
            'name' => ['alpha_num', 'required'],
            'nodes_count' => ['min:1', 'max:3', 'required'],
            'dataCenter' => ['required'],
            'username' => ['required'],
            'password' => ['required'],
            'project_id' => ['required']
        ];

        $this->assertEquals($expected, $this->request->rules());
    }
}
