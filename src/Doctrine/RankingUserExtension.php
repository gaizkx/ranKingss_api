<?php

declare(strict_types=1);

namespace App\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Ranking;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UlidType;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class RankingUserExtension implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Ranking::class) {
            return;
        }

        $this->addWhere($queryBuilder);
        $this->applyDateFilters($queryBuilder, $context);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        if ($resourceClass !== Ranking::class) {
            return;
        }

        $this->addWhere($queryBuilder);
    }

    private function addWhere(QueryBuilder $queryBuilder): void
    {
        $user = $this->security->getUser();

        if ($user === null) {
            return;
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder->andWhere(sprintf('%s.user = :current_user', $rootAlias));
        $queryBuilder->setParameter('current_user', $user->getId(), UlidType::NAME);
    }

    private function applyDateFilters(QueryBuilder $queryBuilder, array $context): void
    {
        $filters = $context['filters'] ?? [];

        $startDate = isset($filters['startDate']) ? $filters['startDate'] : null;
        $endDate = isset($filters['endDate']) ? $filters['endDate'] : null;

        if ($startDate === null && $endDate === null) {
            return;
        }

        if ($endDate === null) {
            $endDate = (new \DateTimeImmutable())->format('Y-m-d');
        }

        try {
            $start = new \DateTimeImmutable($startDate);
            $end = new \DateTimeImmutable($endDate);
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Formato de fecha inválido. Use Y-m-d.');
        }

        $diff = $start->diff($end);
        if ($diff->days > 92) {
            throw new UnprocessableEntityHttpException('El rango de fechas no puede superar los 92 días.');
        }

        $rootAlias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(sprintf('%s.createdAt >= :start_date', $rootAlias))
            ->andWhere(sprintf('%s.createdAt < :end_date', $rootAlias))
            ->setParameter('start_date', $start->setTime(0, 0, 0))
            ->setParameter('end_date', $end->modify('+1 day')->setTime(0, 0, 0));
    }
}
