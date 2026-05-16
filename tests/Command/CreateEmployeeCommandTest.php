<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\Employee;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CreateEmployeeCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testCommandCreatesEmployeeAndOutputsId(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:employee:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Ana García']);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Employee created successfully!', $output);
        $this->assertStringContainsString('Ana García', $output);
    }

    public function testCommandQuietOutputsOnlyUlid(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:employee:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'María Ruiz'], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);

        $this->assertSame(0, $commandTester->getStatusCode());

        $output = trim($commandTester->getDisplay());
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $output);
    }

    public function testCommandPersistsEmployeeInDatabase(): void
    {
        $application = new Application(self::$kernel);
        $command = $application->find('app:employee:create');
        $commandTester = new CommandTester($command);

        $commandTester->execute(['name' => 'Carlos López']);

        $this->assertSame(0, $commandTester->getStatusCode());

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $employee = $entityManager->getRepository(Employee::class)->findOneBy(['name' => 'Carlos López']);

        $this->assertNotNull($employee);
        $this->assertSame('Carlos López', $employee->getName());
    }
}
