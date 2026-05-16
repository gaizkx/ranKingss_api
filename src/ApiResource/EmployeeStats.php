<?php

declare(strict_types=1);

namespace App\ApiResource;

use App\DataTransferObject\HeatmapEntry;

class EmployeeStats
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $totalScore,
        public readonly int $rankingCount,
        public readonly float $averageScore,
        /** @var HeatmapEntry[] */
        public readonly array $heatmap,
    ) {}
}
