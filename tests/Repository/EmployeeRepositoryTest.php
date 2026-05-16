<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Employee;
use App\Repository\EmployeeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

class EmployeeRepositoryTest extends KernelTestCase
{
    use RepositoryTestCase;

    private EmployeeRepository $employeeRepository;

    protected function setUp(): void
    {
        $this->setUpRepository();
        $this->employeeRepository = $this->em()->getRepository(Employee::class);
    }

    public function testCreateAndPersistEmployee(): void
    {
        $employee = $this->createEmployee('Alice');
        $this->em()->flush();
        $ulid = $employee->getId();

        $this->assertInstanceOf(Ulid::class, $ulid);

        $this->em()->clear();
        $found = $this->employeeRepository->find($ulid);

        $this->assertNotNull($found);
        $this->assertSame('Alice', $found->getName());
        $this->assertNotNull($found->getCreatedAt());
    }

    public function testFindAllWithStatsEmpty(): void
    {
        $this->createEmployee('Alice');
        $this->em()->flush();

        $stats = $this->employeeRepository->findAllWithStats();

        $this->assertCount(1, $stats);
        $this->assertSame('Alice', $stats[0]['name']);
        $this->assertEquals(0, $stats[0]['totalRankings']);
        $this->assertNull($stats[0]['averageScore']);
    }

    public function testFindAllWithStatsSingleEmployee(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee('Alice');

        $this->createRanking($user, $employee, 5);
        $this->createRanking($user, $employee, 7);
        $this->createRanking($user, $employee, 9);
        $this->em()->flush();

        $stats = $this->employeeRepository->findAllWithStats();

        $this->assertCount(1, $stats, 'Only one employee exists');
        $this->assertSame('Alice', $stats[0]['name']);
        $this->assertEquals(3, $stats[0]['totalRankings']);
        $this->assertEquals(7.0, $stats[0]['averageScore']);
    }

    public function testFindAllWithStatsMultipleEmployees(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee('Alice');
        $bob = $this->createEmployee('Bob');

        $this->createRanking($user, $employee, 10);
        $this->createRanking($user, $employee, 6);
        $this->createRanking($user, $bob, 8);
        $this->em()->flush();

        $stats = $this->employeeRepository->findAllWithStats();

        $this->assertCount(2, $stats);
        $this->assertSame('Alice', $stats[0]['name']);
        $this->assertSame('Bob', $stats[1]['name']);
        $this->assertEquals(2, $stats[0]['totalRankings']);
        $this->assertEquals(8.0, $stats[0]['averageScore']);
        $this->assertEquals(1, $stats[1]['totalRankings']);
        $this->assertEquals(8.0, $stats[1]['averageScore']);
    }

    public function testFindAllWithStatsEmployeeWithoutRankings(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee('Alice');
        $bob = $this->createEmployee('Bob');

        $this->createRanking($user, $employee, 8);
        $this->createRanking($user, $employee, 6);
        $this->em()->flush();

        $stats = $this->employeeRepository->findAllWithStats();

        $this->assertCount(2, $stats);
        $this->assertSame('Alice', $stats[0]['name']);
        $this->assertSame('Bob', $stats[1]['name']);
        $this->assertEquals(2, $stats[0]['totalRankings']);
        $this->assertEquals(7.0, $stats[0]['averageScore']);
        $this->assertEquals(0, $stats[1]['totalRankings']);
        $this->assertNull($stats[1]['averageScore']);
    }
}
