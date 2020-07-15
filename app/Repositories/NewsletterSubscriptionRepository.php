<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\NewsletterSubscription;

class NewsletterSubscriptionRepository extends BaseRepository
{
    public function __construct(NewsletterSubscription $newsletterSubscription)
    {
        parent::__construct($newsletterSubscription);
    }

    public function firstOrCreate(array $values): NewsletterSubscription
    {
        return $this->model->firstOrCreate($values);
    }
}
