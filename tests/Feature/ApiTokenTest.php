<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function api_token_project_returns_belongs_to()
    {
        $apiToken = factory(ApiToken::class)->make();

        $this->assertInstanceOf(BelongsTo::class, $apiToken->project());
    }

}
