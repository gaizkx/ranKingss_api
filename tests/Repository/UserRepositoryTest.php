<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

class UserRepositoryTest extends KernelTestCase
{
    use RepositoryTestCase;

    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->setUpRepository();
        $this->userRepository = $this->em()->getRepository(User::class);
    }

    public function testCreateAndPersistUser(): void
    {
        $user = $this->createUser('123456789012');
        $this->em()->flush();
        $id = $user->getId();

        $this->assertInstanceOf(Ulid::class, $id);

        $this->em()->clear();
        $found = $this->userRepository->find($id);

        $this->assertNotNull($found);
        $this->assertSame('123456789012', $found->getAccountNumber());
        $this->assertNotNull($found->getCreatedAt());
        $this->assertSame(['ROLE_USER'], $found->getRoles());
    }

    public function testPasswordIsHashed(): void
    {
        $user = $this->createUser();
        $this->em()->flush();

        $this->em()->clear();
        $found = $this->userRepository->find($user->getId());

        $this->assertNotNull($found);
        $this->assertNotSame('test_password', $found->getPassword());
        $this->assertTrue(password_verify('test_password', $found->getPassword()));
    }

    public function testUniqueAccountNumberConstraint(): void
    {
        $this->createUser('111111111111');
        $this->em()->flush();

        $duplicate = new User();
        $duplicate->setAccountNumber('111111111111');
        $duplicate->setPassword('irrelevant');
        $this->em()->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em()->flush();
    }
}
