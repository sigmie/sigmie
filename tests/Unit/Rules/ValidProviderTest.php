<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Rules\ValidProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use PHPUnit\Framework\Constraint\RegularExpression;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class ValidProviderTest  extends TestCase
{
    /**
     * @var ValidProvider
     */
    private $rule;

    /**
     * @var MockObject|FilesystemAdapter
     */
    private $filesystemMock;

    /**
     * @var RegularExpression
     */
    private $filePathArgument;

    public function setUp(): void
    {
        parent::setUp();

        $this->filesystemMock = $this->createMock(FilesystemAdapter::class);

        Storage::shouldReceive('disk')->with('local')->andReturn($this->filesystemMock);

        $this->filePathArgument = $this->matchesRegularExpression('/temp\/.*\.json$/');

        $this->rule = new ValidProvider;
    }

    /**
     * @test
     */
    public function fails_when_provider_aws_or_do()
    {
        $this->assertFalse($this->rule->passes('provider', ['id' => 'aws', 'creds' => 'bar']));
        $this->assertFalse($this->rule->passes('provider', ['id' => 'do', 'creds' => 'bar']));
    }

    /**
     * @test
     */
    public function google_service_account_is_saved_in_temp()
    {
        $this->filesystemMock->method('path')->willReturn('some-path');

        $this->filesystemMock->expects($this->once())->method('put')->with($this->filePathArgument, '[]');

        $this->rule->passes('provider', ['id' => 'google', 'creds' => '[]']);
    }

    /**
     * @test
     */
    public function google_returns_false_when_exception_is_thrown()
    {
        $this->filesystemMock->method('path')->willReturn('not-existing-path');

        $this->assertFalse($this->rule->passes('provider', ['id' => 'google', 'creds' => '[]']));
    }

    /**
     * @test
     */
    public function file_is_deleted_after_result()
    {
        $this->filesystemMock->method('path')->willReturn('some-path');

        $this->filesystemMock->expects($this->once())->method('delete')->with($this->filePathArgument);

        $this->rule->passes('provider', ['id' => 'google', 'creds' => '[]']);
    }

    /**
     * @test
     */
    public function message_returns_string()
    {
        $this->assertEquals('Cloud provider is invalid.', $this->rule->message());
    }
}
