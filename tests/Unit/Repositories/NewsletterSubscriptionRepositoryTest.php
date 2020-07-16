<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\NewsletterSubscription;
use App\Repositories\NewsletterSubscriptionRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\NeedsModel;

class NewsletterSubscriptionRepositoryTest extends TestCase
{
    use NeedsModel;

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

        $this->model = $this->model(NewsletterSubscription::class);
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
