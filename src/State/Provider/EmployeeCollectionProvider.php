<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\EmployeeListItem;
use App\Repository\EmployeeRepository;

final class EmployeeCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $rows = $this->employeeRepository->findAllWithStats();

        return array_map(
            fn (array $row) => new EmployeeListItem(
                id: $row['id'],
                name: $row['name'],
                totalRankings: (int) $row['totalRankings'],
                averageScore: $row['averageScore'] !== null ? (float) $row['averageScore'] : 0.0,
            ),
            $rows,
        );
    }
}
