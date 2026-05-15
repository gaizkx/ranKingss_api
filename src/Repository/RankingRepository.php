<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Ranking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Component\Uid\Ulid;

/** @extends ServiceEntityRepository<Ranking> */
class RankingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ranking::class);
    }

    public function countTodayByUser(User $user): int
    {
        $start = new \DateTimeImmutable();
        $start = $start->setTime(0, 0, 0);
        $end = $start->modify('+1 day');

        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.user = :user')
            ->andWhere('r.createdAt >= :start')
            ->andWhere('r.createdAt < :end')
            ->setParameter('user', $user->getId(), UlidType::NAME)
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return Ranking[]
     */
    public function findByUserAndDateRange(User $user, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.user = :user')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt <= :to')
            ->setParameter('user', $user->getId(), UlidType::NAME)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('r.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    public function findStatsForEmployee(Ulid $employeeId, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $rows = $this->createQueryBuilder('r')
            ->select('r.score', 'r.createdAt')
            ->where('r.employee = :employeeId')
            ->andWhere('r.createdAt >= :from')
            ->andWhere('r.createdAt < :to')
            ->setParameter('employeeId', $employeeId, UlidType::NAME)
            ->setParameter('from', $from)
            ->setParameter('to', $to->modify('+1 day'))
            ->getQuery()
            ->getResult();

        $totalScore = 0;
        $byDate = [];

        foreach ($rows as $row) {
            $date = $row['createdAt']->format('Y-m-d');
            $totalScore += (int) $row['score'];

            $byDate[$date] ??= ['total' => 0, 'count' => 0];
            $byDate[$date]['total'] += (int) $row['score'];
            $byDate[$date]['count']++;
        }

        $rankingCount = count($rows);

        $byDateResult = [];
        ksort($byDate);
        foreach ($byDate as $date => $data) {
            $byDateResult[] = [
                'date' => $date,
                'avgScore' => $data['total'] / $data['count'],
                'count' => $data['count'],
            ];
        }

        return [
            'totalScore' => $totalScore,
            'rankingCount' => $rankingCount,
            'byDate' => $byDateResult,
        ];
    }
}
