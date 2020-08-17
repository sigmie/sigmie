<?php

declare(strict_types=1);

namespace Tests\Unit\Requests\Http\UpdateClusterTest;

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
        $colonRegex = '/:.*/';
        $this->assertEquals([
            'nodes_count' => ['min:1', 'max:3', 'required'],
            'data_center' => ['required'],
            'username' => ['required', "not_regex:{$colonRegex}"],
            'password' => ['required', 'min:4', 'max:8'],
        ], $this->request->rules());

        $this->assertEquals(1, preg_match($colonRegex, 'foo:bar'));
        $this->assertEquals(1, preg_match($colonRegex, 'f::o:bar'));
        $this->assertEquals(1, preg_match($colonRegex, ':foobar'));
        $this->assertEquals(0, preg_match($colonRegex, 'foobar'));
        $this->assertEquals(0, preg_match($colonRegex, 'usern/ex-dhs'));
    }
}
