<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreProject;
use App\Rules\ValidProvider;
use Tests\TestCase;

class StoreProjectTest extends TestCase
{
    /**
     * @var StoreProject
     */
    private $request;

    public function setUp(): void
    {
        parent::setUp();

        $this->request = new StoreProject();
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
            'name' => ['required', 'regex:/^[a-zA-Z0-9-_]*$/i'],
            'provider' => [new ValidProvider()],
            'description' => []
        ];

        $this->assertEquals($expected, $this->request->rules());
    }
}
