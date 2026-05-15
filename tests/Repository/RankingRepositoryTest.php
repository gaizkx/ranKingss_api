<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Ranking;
use App\Repository\RankingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

class RankingRepositoryTest extends KernelTestCase
{
    use RepositoryTestCase;

    private RankingRepository $rankingRepository;

    protected function setUp(): void
    {
        $this->setUpRepository();
        $this->rankingRepository = $this->em()->getRepository(Ranking::class);
    }

    public function testCreateAndPersistRanking(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();
        $ranking = $this->createRanking($user, $employee, 7);
        $this->em()->flush();
        $id = $ranking->getId();

        $this->assertInstanceOf(Ulid::class, $id);

        $this->em()->clear();
        $found = $this->rankingRepository->find($id);

        $this->assertNotNull($found);
        $this->assertSame(7, $found->getScore());
        $this->assertSame($user->getId()->toBinary(), $found->getUser()->getId()->toBinary());
        $this->assertNotNull($found->getCreatedAt());
    }

    public function testScoreBoundaries(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();

        $ranking0 = $this->createRanking($user, $employee, 0);
        $ranking10 = $this->createRanking($user, $employee, 10);
        $this->em()->flush();
        $this->em()->clear();

        $found0 = $this->rankingRepository->find($ranking0->getId());
        $this->assertSame(0, $found0->getScore());

        $found10 = $this->rankingRepository->find($ranking10->getId());
        $this->assertSame(10, $found10->getScore());
    }

    public function testCountTodayByUserZero(): void
    {
        $user = $this->createUser();
        $this->em()->flush();

        $count = $this->rankingRepository->countTodayByUser($user);
        $this->assertSame(0, $count);
    }

    public function testCountTodayByUserSingle(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();
        $this->createRanking($user, $employee, 5);
        $this->em()->flush();

        $count = $this->rankingRepository->countTodayByUser($user);
        $this->assertSame(1, $count);
    }

    public function testCountTodayByUserMultiple(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();

        for ($i = 0; $i < 4; ++$i) {
            $this->createRanking($user, $employee, $i);
        }
        $this->em()->flush();

        $count = $this->rankingRepository->countTodayByUser($user);
        $this->assertSame(4, $count);
    }

    public function testCountTodayByUserExcludesYesterday(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();
        $yesterday = (new \DateTimeImmutable())->modify('-1 day');
        $this->createRanking($user, $employee, 5, $yesterday->format('Y-m-d') . ' 10:00:00');
        $this->em()->flush();

        $count = $this->rankingRepository->countTodayByUser($user);
        $this->assertSame(0, $count);
    }

    public function testCountTodayByUserExcludesOtherUser(): void
    {
        $userA = $this->createUser();
        $userB = $this->createUser();
        $employee = $this->createEmployee();

        $this->createRanking($userA, $employee, 5);
        $this->em()->flush();

        $countB = $this->rankingRepository->countTodayByUser($userB);
        $this->assertSame(0, $countB);
    }

    public function testCountTodayByUserDetectLimit(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();

        for ($i = 0; $i < 5; ++$i) {
            $this->createRanking($user, $employee, $i);
        }
        $this->em()->flush();

        $count = $this->rankingRepository->countTodayByUser($user);
        $this->assertSame(5, $count);
    }

    public function testFindByUserAndDateRange(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();

        $this->createRanking($user, $employee, 5, '2026-05-10 10:00:00');
        $this->createRanking($user, $employee, 7, '2026-05-11 10:00:00');
        $this->createRanking($user, $employee, 9, '2026-05-12 10:00:00');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-05-10 00:00:00');
        $to = new \DateTimeImmutable('2026-05-11 23:59:59');

        $rankings = $this->rankingRepository->findByUserAndDateRange($user, $from, $to);

        $this->assertCount(2, $rankings);
        $this->assertSame(7, $rankings[0]->getScore());
        $this->assertSame(5, $rankings[1]->getScore());
    }

    public function testFindByUserAndDateRangeEmpty(): void
    {
        $user = $this->createUser();
        $employee = $this->createEmployee();
        $this->createRanking($user, $employee, 5, '2026-05-10 10:00:00');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-06-01 00:00:00');
        $to = new \DateTimeImmutable('2026-06-30 23:59:59');

        $rankings = $this->rankingRepository->findByUserAndDateRange($user, $from, $to);

        $this->assertCount(0, $rankings);
    }

    public function testFindStatsForEmployeeEmpty(): void
    {
        $employee = $this->createEmployee('Alice');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-01-01 00:00:00');
        $to = new \DateTimeImmutable('2026-12-31 00:00:00');

        $stats = $this->rankingRepository->findStatsForEmployee($employee->getId(), $from, $to);

        $this->assertSame(0, $stats['totalScore']);
        $this->assertSame(0, $stats['rankingCount']);
        $this->assertSame([], $stats['byDate']);
    }

    public function testFindStatsForEmployeeSingleDay(): void
    {
        $employee = $this->createEmployee();
        $user = $this->createUser();

        $this->createRanking($user, $employee, 3, '2026-05-10 08:00:00');
        $this->createRanking($user, $employee, 7, '2026-05-10 14:00:00');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-05-10 00:00:00');
        $to = new \DateTimeImmutable('2026-05-10 00:00:00');

        $stats = $this->rankingRepository->findStatsForEmployee($employee->getId(), $from, $to);

        $this->assertSame(10, $stats['totalScore']);
        $this->assertSame(2, $stats['rankingCount']);
        $this->assertCount(1, $stats['byDate']);
        $this->assertSame('2026-05-10', $stats['byDate'][0]['date']);
        $this->assertEquals(5.0, $stats['byDate'][0]['avgScore']);
        $this->assertSame(2, $stats['byDate'][0]['count']);
    }

    public function testFindStatsForEmployeeMultipleDays(): void
    {
        $employee = $this->createEmployee();
        $user = $this->createUser();

        $this->createRanking($user, $employee, 8, '2026-05-10 10:00:00');
        $this->createRanking($user, $employee, 5, '2026-05-11 08:00:00');
        $this->createRanking($user, $employee, 9, '2026-05-11 14:00:00');
        $this->createRanking($user, $employee, 3, '2026-05-12 12:00:00');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-05-10 00:00:00');
        $to = new \DateTimeImmutable('2026-05-12 00:00:00');

        $stats = $this->rankingRepository->findStatsForEmployee($employee->getId(), $from, $to);

        $this->assertSame(25, $stats['totalScore']);
        $this->assertSame(4, $stats['rankingCount']);
        $this->assertCount(3, $stats['byDate']);

        $this->assertSame('2026-05-10', $stats['byDate'][0]['date']);
        $this->assertEquals(8.0, $stats['byDate'][0]['avgScore']);
        $this->assertSame(1, $stats['byDate'][0]['count']);

        $this->assertSame('2026-05-11', $stats['byDate'][1]['date']);
        $this->assertEquals(7.0, $stats['byDate'][1]['avgScore']);
        $this->assertSame(2, $stats['byDate'][1]['count']);

        $this->assertSame('2026-05-12', $stats['byDate'][2]['date']);
        $this->assertEquals(3.0, $stats['byDate'][2]['avgScore']);
        $this->assertSame(1, $stats['byDate'][2]['count']);
    }

    public function testFindStatsForEmployeeDateRange(): void
    {
        $employee = $this->createEmployee();
        $user = $this->createUser();

        $this->createRanking($user, $employee, 10, '2026-05-09 10:00:00');
        $this->createRanking($user, $employee, 4, '2026-05-10 10:00:00');
        $this->createRanking($user, $employee, 6, '2026-05-11 10:00:00');
        $this->createRanking($user, $employee, 8, '2026-05-13 10:00:00');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-05-10 00:00:00');
        $to = new \DateTimeImmutable('2026-05-12 00:00:00');

        $stats = $this->rankingRepository->findStatsForEmployee($employee->getId(), $from, $to);

        $this->assertSame(10, $stats['totalScore']);
        $this->assertSame(2, $stats['rankingCount']);
        $this->assertCount(2, $stats['byDate']);
    }

    public function testFindStatsForEmployeeScoreConsistency(): void
    {
        $employee = $this->createEmployee();
        $user = $this->createUser();

        $this->createRanking($user, $employee, 0, '2026-05-10 10:00:00');
        $this->createRanking($user, $employee, 10, '2026-05-10 11:00:00');
        $this->createRanking($user, $employee, 1, '2026-05-10 12:00:00');
        $this->createRanking($user, $employee, 9, '2026-05-10 13:00:00');
        $this->em()->flush();

        $from = new \DateTimeImmutable('2026-05-10 00:00:00');
        $to = new \DateTimeImmutable('2026-05-10 00:00:00');

        $stats = $this->rankingRepository->findStatsForEmployee($employee->getId(), $from, $to);

        $this->assertSame(20, $stats['totalScore']);
        $this->assertSame(4, $stats['rankingCount']);
        $this->assertEquals(5.0, $stats['byDate'][0]['avgScore']);
    }
}
