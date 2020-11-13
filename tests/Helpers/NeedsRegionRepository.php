<?php

declare(strict_types=1);

namespace Tests\Helpers;

use App\Repositories\RegionRepository;
use Illuminate\Support\Collection;
use PHPUnit\Framework\MockObject\MockObject;
use Sigmie\App\Core\Cloud\Regions\Asia;
use Sigmie\App\Core\Cloud\Regions\Australia;

trait NeedsRegionRepository
{
    private Collection $regions;

    /**
     * @var RegionRepository|MockObject
     */
    private $regionRepositoryMock;

    final public function regionRepository()
    {
        $this->regions = collect([
            [
                'id' => 1,
                'class' => Asia::class,
                'name' => 'Asia',
            ],
            [
                'id' => 2,
                'class' => Australia::class,
                'name' => 'Australia',
            ]
        ]);

        $regionRepositoryMock = $this->createMock(RegionRepository::class);
        $regionRepositoryMock->method('all')->willReturn($this->regions);

        $this->regionRepositoryMock = $regionRepositoryMock;
    }
}
