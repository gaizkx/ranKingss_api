<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Repository\RankingRepository;
use App\State\Processor\RankingCreateProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RankingRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Post(processor: RankingCreateProcessor::class),
    ],
    normalizationContext: ['groups' => ['ranking:read']],
    denormalizationContext: ['groups' => ['ranking:write']],
    paginationEnabled: false,
    security: "is_granted('ROLE_USER')",
)]
class Ranking
{
    use UlidIdTrait {
        __construct as private __generateId;
    }

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['ranking:read', 'ranking:write'])]
    private Employee $employee;

    #[ORM\Column(type: 'smallint')]
    #[Assert\Range(min: 0, max: 10)]
    #[Groups(['ranking:read', 'ranking:write'])]
    private int $score;

    #[ORM\Column]
    #[Groups(['ranking:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->__generateId();
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getEmployee(): Employee
    {
        return $this->employee;
    }

    public function setEmployee(Employee $employee): self
    {
        $this->employee = $employee;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
