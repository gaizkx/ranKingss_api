<?php

declare(strict_types=1);

namespace App\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Ranking;
use App\Repository\RankingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class RankingCreateProcessor implements ProcessorInterface
{
    public function __construct(
        private Security $security,
        private RankingRepository $rankingRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Ranking
    {
        assert($data instanceof Ranking);

        $user = $this->security->getUser();

        if ($this->rankingRepository->countTodayByUser($user) >= 5) {
            throw new UnprocessableEntityHttpException('Límite diario de 5 rankings alcanzado.');
        }

        $data->setUser($user);
        $data->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
