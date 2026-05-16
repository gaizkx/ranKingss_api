<?php

declare(strict_types=1);

namespace App\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\EmployeeStats;
use App\DataTransferObject\HeatmapEntry;
use App\Entity\Employee;
use App\Repository\RankingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Ulid;

final readonly class EmployeeStatsProvider implements ProviderInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RankingRepository $rankingRepository,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): EmployeeStats
    {
        $id = $uriVariables['id'] instanceof Ulid ? $uriVariables['id'] : new Ulid($uriVariables['id']);

        $employee = $this->entityManager->getRepository(Employee::class)->find($id);

        if ($employee === null) {
            throw new NotFoundHttpException('Employee not found.');
        }

        $filters = $context['filters'] ?? [];
        $startDate = $filters['startDate'] ?? null;
        $endDate = $filters['endDate'] ?? null;

        if ($endDate === null) {
            $endDate = (new \DateTimeImmutable())->format('Y-m-d');
        }

        try {
            $end = new \DateTimeImmutable($endDate);
        } catch (\Exception) {
            throw new UnprocessableEntityHttpException('Formato de fecha inválido. Use Y-m-d.');
        }

        if ($startDate === null) {
            $start = $end->modify('-92 days');
        } else {
            try {
                $start = new \DateTimeImmutable($startDate);
            } catch (\Exception) {
                throw new UnprocessableEntityHttpException('Formato de fecha inválido. Use Y-m-d.');
            }

            $diff = $start->diff($end);
            if ($diff->days > 92) {
                throw new UnprocessableEntityHttpException('El rango de fechas no puede superar los 92 días.');
            }
        }

        $stats = $this->rankingRepository->findStatsForEmployee($id, $start, $end);

        $byDate = [];
        foreach ($stats['byDate'] as $row) {
            $byDate[$row['date']] = $row;
        }

        $datePeriod = new \DatePeriod($start, new \DateInterval('P1D'), $end->modify('+1 day'));
        $heatmap = [];

        foreach ($datePeriod as $date) {
            $key = $date->format('Y-m-d');
            if (isset($byDate[$key])) {
                $heatmap[] = new HeatmapEntry(
                    date: $key,
                    avgScore: $byDate[$key]['avgScore'],
                    rankingCount: $byDate[$key]['count'],
                );
            } else {
                $heatmap[] = new HeatmapEntry(
                    date: $key,
                    avgScore: 0.0,
                    rankingCount: 0,
                );
            }
        }

        $rankingCount = $stats['rankingCount'];
        $totalScore = $stats['totalScore'];
        $averageScore = $rankingCount > 0 ? (float) $totalScore / $rankingCount : 0.0;

        return new EmployeeStats(
            id: (string) $uriVariables['id'],
            name: $employee->getName(),
            totalScore: $totalScore,
            rankingCount: $rankingCount,
            averageScore: $averageScore,
            heatmap: $heatmap,
        );
    }
}
