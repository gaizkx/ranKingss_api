<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Employee;
use App\Entity\Ranking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Employee> */
class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function findAllWithStats(): array
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e.id, e.name, COUNT(r.id) AS totalRankings, AVG(r.score) AS averageScore')
            ->leftJoin(Ranking::class, 'r', 'WITH', 'r.employee = e')
            ->groupBy('e.id', 'e.name')
            ->orderBy('e.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
