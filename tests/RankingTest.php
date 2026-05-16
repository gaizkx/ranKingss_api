<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Ranking;
use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Employee;
use App\Entity\User;

class RankingTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    private function getToken(User $user, Client $client): string
    {
        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'account' => $user->getAccountNumber(),
                'password' => 'test_password',
            ],
        ]);

        return $response->toArray()['token'];
    }

    private function createUser(string $accountNumber, string $hashedPassword = '$2y$13$9Fs/hEWENck3e.7uSrXDUulUN55ORadXO01vTkYXGpfpQU1vf3MzS'): User
    {
        $user = new User();
        $user->setAccountNumber($accountNumber);
        $user->setPassword($hashedPassword);
        self::getContainer()->get('doctrine')->getManager()->persist($user);

        return $user;
    }

    private function createEmployee(string $name): Employee
    {
        $employee = new Employee();
        $employee->setName($name);
        self::getContainer()->get('doctrine')->getManager()->persist($employee);

        return $employee;
    }

    private function createRanking(User $user, Employee $employee, int $score): void
    {
        $ranking = new Ranking();
        $ranking->setUser($user);
        $ranking->setEmployee($employee);
        $ranking->setScore($score);
        $ranking->setCreatedAt(new \DateTimeImmutable());
        self::getContainer()->get('doctrine')->getManager()->persist($ranking);
    }

    public function testPostRankingWithoutAuthReturns401(): void
    {
        $employee = $this->createEmployee('Test Employee');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $employeeIri = '/employees/' . $employee->getId();

        self::createClient()->request('POST', '/rankings', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'employee' => $employeeIri,
                'score' => 7,
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testPostRankingCreatesSuccessfully(): void
    {
        $client = self::createClient();

        $user = $this->createUser('333333333333');
        $employee = $this->createEmployee('Test Employee');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user, $client);
        $response = $client->request('POST', '/rankings', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json', 'Accept' => 'application/ld+json'],
            'json' => [
                'employee' => '/employees/' . $employee->getId(),
                'score' => 8,
            ],
        ]);

        $this->assertResponseStatusCodeSame(201);
        $data = $response->toArray();
        $this->assertSame(8, $data['score']);
        $this->assertStringStartsWith('/employees/', $data['employee']);
        $this->assertStringStartsWith('/rankings/', $data['@id']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayNotHasKey('user', $data);
    }

    public function testPostRankingExceedsDailyLimitReturns422(): void
    {
        $client = self::createClient();

        $user = $this->createUser('444444444444');
        $employee = $this->createEmployee('Daily Limit Employee');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user, $client);
        $employeeIri = '/employees/' . $employee->getId();
        $body = ['json' => ['employee' => $employeeIri, 'score' => 5], 'headers' => ['Content-Type' => 'application/ld+json']];

        for ($i = 0; $i < 5; $i++) {
            $client->request('POST', '/rankings', ['auth_bearer' => $token] + $body);
        }

        $response = $client->request('POST', '/rankings', ['auth_bearer' => $token] + $body);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testPostRankingWithScoreBelowZeroReturns422(): void
    {
        $client = self::createClient();

        $user = $this->createUser('999999999991');
        $employee = $this->createEmployee('Score Test');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user, $client);

        $client->request('POST', '/rankings', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'employee' => '/employees/' . $employee->getId(),
                'score' => -1,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testPostRankingWithScoreAboveTenReturns422(): void
    {
        $client = self::createClient();

        $user = $this->createUser('999999999992');
        $employee = $this->createEmployee('Score Test 2');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user, $client);

        $client->request('POST', '/rankings', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'employee' => '/employees/' . $employee->getId(),
                'score' => 11,
            ],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetRankingsReturnsOnlyOwnRankings(): void
    {
        $client = self::createClient();

        $user1 = $this->createUser('555555555555');
        $user2 = $this->createUser('666666666666');
        $employee = $this->createEmployee('Shared Employee');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $this->createRanking($user1, $employee, 1);
        $this->createRanking($user1, $employee, 2);
        $this->createRanking($user2, $employee, 9);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user1, $client);

        $response = $client->request('GET', '/rankings', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertCount(2, $data['member']);
        $scores = array_map(fn ($r) => $r['score'], $data['member']);
        sort($scores);
        $this->assertSame([1, 2], $scores);
    }

    public function testGetRankingsWithDateFilter(): void
    {
        $client = self::createClient();

        $user = $this->createUser('777777777777');
        $employee = $this->createEmployee('Date Filter Employee');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $ranking1 = new Ranking();
        $ranking1->setUser($user);
        $ranking1->setEmployee($employee);
        $ranking1->setScore(5);
        $ranking1->setCreatedAt(new \DateTimeImmutable('-5 days'));
        self::getContainer()->get('doctrine')->getManager()->persist($ranking1);

        $ranking2 = new Ranking();
        $ranking2->setUser($user);
        $ranking2->setEmployee($employee);
        $ranking2->setScore(6);
        $ranking2->setCreatedAt(new \DateTimeImmutable('-1 day'));
        self::getContainer()->get('doctrine')->getManager()->persist($ranking2);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user, $client);

        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $threeDaysAgo = (new \DateTimeImmutable('-3 days'))->format('Y-m-d');

        $response = $client->request('GET', '/rankings', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(2, $data['member']);

        $response = $client->request('GET', '/rankings?startDate=' . $threeDaysAgo . '&endDate=' . $today, [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();
        $this->assertCount(1, $data['member']);
        $this->assertSame(6, $data['member'][0]['score']);
    }

    public function testGetRankingsWithDateRangeExceeds92DaysReturns422(): void
    {
        $client = self::createClient();

        $user = $this->createUser('888888888888');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $token = $this->getToken($user, $client);

        $response = $client->request('GET', '/rankings?startDate=2024-01-01&endDate=2024-12-31', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
