<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Uid\Ulid;

final readonly class EmployeeListItem
{
    public function __construct(
        public Ulid $id,
        public string $name,
        public int $totalRankings,
        public float $averageScore,
    ) {}
}
