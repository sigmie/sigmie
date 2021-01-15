<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\NewsletterSubscription;
use App\Repositories\NewsletterSubscriptionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\WithModelMock;

class NewsletterSubscriptionRepositoryTest extends TestCase
{
    use WithModelMock;

    /**
     * @var NewsletterSubscriptionRepository
     */
    private $repository;

    /**
     * @var MockObject|NewsletterSubscription
     */
    private $model;

    public function setUp(): void
    {
        parent::setUp();

        $this->model = $this->withModelMock(NewsletterSubscription::class);
        $this->model->method('firstOrCreate')->willReturnSelf();

        $this->repository = new NewsletterSubscriptionRepository($this->model);
    }

    /**
     * @test
     */
    public function first_or_create_is_called_on_instance_with_values(): void
    {
        $this->model->expects($this->once())->method('firstOrCreate')->with(['foo' => 'bar']);

        $this->repository->firstOrCreate(['foo' => 'bar']);
    }
}
