<?php

declare(strict_types=1);

namespace Tests\Unit\Requests;

use App\Http\Requests\UpdateCluster;
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
        $this->assertEquals([
            'nodes_count' => ['min:1', 'max:3', 'required'],
            'data_center' => ['required'],
            'username' => ['required'],
            'password' => ['required']
        ], $this->request->rules());
    }
}
