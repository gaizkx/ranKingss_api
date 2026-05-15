<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EmployeeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EmployeeRepository::class)]
class Employee
{
    use UlidIdTrait {
        __construct as private __generateId;
    }

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    private ?int $totalRankings = null;

    private ?float $averageScore = null;

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

    public function getTotalRankings(): ?int
    {
        return $this->totalRankings;
    }

    public function setTotalRankings(?int $totalRankings): self
    {
        $this->totalRankings = $totalRankings;

        return $this;
    }

    public function getAverageScore(): ?float
    {
        return $this->averageScore;
    }

    public function setAverageScore(?float $averageScore): self
    {
        $this->averageScore = $averageScore;

        return $this;
    }
}
