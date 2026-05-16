<?php

declare(strict_types=1);

namespace App\DataTransferObject;

final readonly class HeatmapEntry
{
    public function __construct(
        public string $date,
        public float $avgScore,
        public int $rankingCount,
    ) {}
}
