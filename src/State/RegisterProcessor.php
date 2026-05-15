<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Register;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        assert($data instanceof Register);

        $existing = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['accountNumber' => $data->accountNumber]);

        if ($existing !== null) {
            throw new ConflictHttpException('Account number already exists.');
        }

        $user = new User();
        $user->setAccountNumber($data->accountNumber);
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
