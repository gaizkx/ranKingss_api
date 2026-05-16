<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:employee:create')]
class CreateEmployeeCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Full name of the employee');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        $employee = new Employee();
        $employee->setName($name);

        $this->entityManager->persist($employee);
        $this->entityManager->flush();

        $ulid = $employee->getId()->toRfc4122();

        if ($output->isQuiet()) {
            $output->writeln($ulid, OutputInterface::VERBOSITY_QUIET);
        } else {
            $symfonyStyle = new SymfonyStyle($input, $output);
            $symfonyStyle->success('Employee created successfully!');
            $symfonyStyle->table(
                ['Name', 'ID'],
                [[$name, $ulid]],
            );
        }

        return Command::SUCCESS;
    }
}
