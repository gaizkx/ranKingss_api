<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Ping;
use Symfony\Bundle\SecurityBundle\Security;

final class PingProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Ping
    {
        $ping = new Ping();

        $user = $this->security->getUser();
        if ($user !== null) {
            $ping->user_id = $user->getUserIdentifier();
        }

        return $ping;
    }
}
