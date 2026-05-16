<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\Employee;
use App\Entity\User;

class EmployeeStatsTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    private function getToken(Client $client): string
    {
        $user = new User();
        $user->setAccountNumber('000000000001');
        $user->setPassword('$2y$13$9Fs/hEWENck3e.7uSrXDUulUN55ORadXO01vTkYXGpfpQU1vf3MzS');
        self::getContainer()->get('doctrine')->getManager()->persist($user);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'account' => '000000000001',
                'password' => 'test_password',
            ],
        ]);

        return $response->toArray()['token'];
    }

    public function testGetStatsWithoutAuthReturns401(): void
    {
        $employee = new Employee();
        $employee->setName('Test');
        self::getContainer()->get('doctrine')->getManager()->persist($employee);
        self::getContainer()->get('doctrine')->getManager()->flush();

        self::createClient()->request('GET', '/employees/' . $employee->getId() . '/stats', [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetStatsWithRankings(): void
    {
        $client = self::createClient();
        $token = $this->getToken($client);

        $employee = new Employee();
        $employee->setName('Alice');
        self::getContainer()->get('doctrine')->getManager()->persist($employee);

        $user = new User();
        $user->setAccountNumber('000000000002');
        $user->setPassword('$2y$13$9Fs/hEWENck3e.7uSrXDUulUN55ORadXO01vTkYXGpfpQU1vf3MzS');
        self::getContainer()->get('doctrine')->getManager()->persist($user);

        $ranking1 = new \App\Entity\Ranking();
        $ranking1->setUser($user);
        $ranking1->setEmployee($employee);
        $ranking1->setScore(7);
        $ranking1->setCreatedAt(new \DateTimeImmutable('-1 day'));
        self::getContainer()->get('doctrine')->getManager()->persist($ranking1);

        $ranking2 = new \App\Entity\Ranking();
        $ranking2->setUser($user);
        $ranking2->setEmployee($employee);
        $ranking2->setScore(9);
        $ranking2->setCreatedAt(new \DateTimeImmutable('-1 day'));
        self::getContainer()->get('doctrine')->getManager()->persist($ranking2);

        self::getContainer()->get('doctrine')->getManager()->flush();

        $response = $client->request('GET', '/employees/' . $employee->getId() . '/stats', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertNotEmpty($data['id']);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame(16, $data['totalScore']);
        $this->assertSame(2, $data['rankingCount']);
        $this->assertEquals(8.0, $data['averageScore']);
        $this->assertNotEmpty($data['heatmap']);
    }

    public function testGetStatsWithoutRankings(): void
    {
        $client = self::createClient();
        $token = $this->getToken($client);

        $employee = new Employee();
        $employee->setName('Bob');
        self::getContainer()->get('doctrine')->getManager()->persist($employee);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $response = $client->request('GET', '/employees/' . $employee->getId() . '/stats', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertSame('Bob', $data['name']);
        $this->assertSame(0, $data['totalScore']);
        $this->assertSame(0, $data['rankingCount']);
        $this->assertEquals(0.0, $data['averageScore']);
        $this->assertNotEmpty($data['heatmap']);
    }

    public function testGetStatsWithDateRangeExceeds92DaysReturns422(): void
    {
        $client = self::createClient();
        $token = $this->getToken($client);

        $employee = new Employee();
        $employee->setName('Charlie');
        self::getContainer()->get('doctrine')->getManager()->persist($employee);
        self::getContainer()->get('doctrine')->getManager()->flush();

        $response = $client->request('GET', '/employees/' . $employee->getId() . '/stats?startDate=2024-01-01&endDate=2024-12-31', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    public function testGetStatsForNonExistingEmployeeReturns404(): void
    {
        $client = self::createClient();
        $token = $this->getToken($client);

        $client->request('GET', '/employees/01ARZ3NDEKTSV4RRFFQ69G5FAV/stats', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(404);
    }
}
