<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use App\ApiResource\EmployeeListItem;
use App\ApiResource\EmployeeStats;
use App\Repository\EmployeeRepository;
use App\State\Provider\EmployeeCollectionProvider;
use App\State\Provider\EmployeeStatsProvider;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: EmployeeCollectionProvider::class,
            output: EmployeeListItem::class,
        ),
        new Get(
            uriTemplate: '/employees/{id}/stats',
            provider: EmployeeStatsProvider::class,
            output: EmployeeStats::class,
        ),
        new Get(
            normalizationContext: ['groups' => ['employee:read']],
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
    #[Groups(['employee:read'])]
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
