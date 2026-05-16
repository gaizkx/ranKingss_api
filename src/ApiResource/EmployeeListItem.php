<?php

declare(strict_types=1);

namespace App\ApiResource;

use Symfony\Component\Uid\Ulid;

final class EmployeeListItem
{
    public function __construct(
        public readonly Ulid $id,
        public readonly string $name,
        public readonly int $totalRankings,
        public readonly float $averageScore,
    ) {}
}
