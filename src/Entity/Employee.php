<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\ApiResource\EmployeeListItem;
use App\Repository\EmployeeRepository;
use App\State\Provider\EmployeeCollectionProvider;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: EmployeeCollectionProvider::class,
            output: EmployeeListItem::class,
        ),
    ],
    paginationEnabled: false,
    security: "is_granted('ROLE_USER')",
)]
class Employee
{
    use UlidIdTrait {
        __construct as private __generateId;
    }

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->__generateId();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
