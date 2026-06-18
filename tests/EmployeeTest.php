<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\ApiKeyAwareTestCase;
use App\Entity\Employee;
use App\Entity\User;

class EmployeeTest extends ApiKeyAwareTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    private function createEmployee(string $name): Employee
    {
        $container = self::getContainer();
        $employee = new Employee();
        $employee->setName($name);
        $container->get('doctrine')->getManager()->persist($employee);

        return $employee;
    }

    private function getToken(): string
    {
        $client = self::createClient();
        $container = self::getContainer();
        $user = new User();
        $user->setAccountNumber('222222222222');
        $user->setPassword('$2y$13$9Fs/hEWENck3e.7uSrXDUulUN55ORadXO01vTkYXGpfpQU1vf3MzS');
        $container->get('doctrine')->getManager()->persist($user);
        $container->get('doctrine')->getManager()->flush();

        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'account' => '222222222222',
                'password' => 'test_password',
            ],
        ]);

        return $response->toArray()['token'];
    }

    public function testGetEmployeesWithoutAuthReturns401(): void
    {
        self::createClient()->request('GET', '/employees', [
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }

    public function testGetEmployeesReturnsListWithStats(): void
    {
        $client = self::createClient();

        $token = $this->getToken();

        $this->createEmployee('Alice');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $response = $client->request('GET', '/employees', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $this->assertArrayHasKey('member', $data);
        $this->assertCount(1, $data['member']);
        $this->assertSame('Alice', $data['member'][0]['name']);
        $this->assertSame(0, $data['member'][0]['totalRankings']);
        $this->assertEquals(0.0, $data['member'][0]['averageScore']);
        $this->assertArrayHasKey('id', $data['member'][0]);
    }

    public function testGetEmployeesWithMultipleEmployees(): void
    {
        $client = self::createClient();

        $token = $this->getToken();

        $this->createEmployee('Bob');
        $this->createEmployee('Alice');
        $this->createEmployee('Charlie');
        self::getContainer()->get('doctrine')->getManager()->flush();

        $response = $client->request('GET', '/employees', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);

        $this->assertResponseIsSuccessful();
        $data = $response->toArray();

        $members = $data['member'];
        $this->assertCount(3, $members);
        $this->assertSame('Alice', $members[0]['name']);
        $this->assertSame('Bob', $members[1]['name']);
        $this->assertSame('Charlie', $members[2]['name']);
    }
}
