<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Employee;
use App\Entity\Ranking;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

trait RepositoryTestCase
{
    private static int $accountCounter = 0;

    private ?EntityManagerInterface $entityManager = null;

    protected function setUpRepository(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    protected function em(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function createUser(?string $accountNumber = null): User
    {
        $user = new User();
        $user->setAccountNumber($accountNumber ?? $this->generateAccountNumber());
        $user->setPassword(password_hash('test_password', PASSWORD_BCRYPT));
        $this->em()->persist($user);
        return $user;
    }

    protected function createEmployee(?string $name = null): Employee
    {
        $employee = new Employee();
        $employee->setName($name ?? 'Test Employee');
        $this->em()->persist($employee);
        return $employee;
    }

    protected function createRanking(User $user, Employee $employee, int $score, ?string $dateString = null): Ranking
    {
        $ranking = new Ranking();
        $ranking->setUser($user);
        $ranking->setEmployee($employee);
        $ranking->setScore($score);
        $ranking->setCreatedAt(
            $dateString !== null
                ? new \DateTimeImmutable($dateString)
                : new \DateTimeImmutable(),
        );
        $this->em()->persist($ranking);
        return $ranking;
    }

    private function generateAccountNumber(): string
    {
        ++self::$accountCounter;
        return str_pad((string) self::$accountCounter, 12, '0', \STR_PAD_LEFT);
    }
}
