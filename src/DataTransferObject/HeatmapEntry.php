<?php

declare(strict_types=1);

namespace App\DataTransferObject;

final class HeatmapEntry
{
    public function __construct(
        public readonly string $date,
        public readonly float $avgScore,
        public readonly int $rankingCount,
    ) {}
}
