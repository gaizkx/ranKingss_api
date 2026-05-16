<?php

declare(strict_types=1);

namespace App\State;

use Symfony\Component\Security\Core\User\UserInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\Ping;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class PingProvider implements ProviderInterface
{
    public function __construct(
        private Security $security,
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Ping
    {
        $ping = new Ping();

        $user = $this->security->getUser();
        if ($user instanceof UserInterface) {
            $ping->user_id = $user->getUserIdentifier();
        }

        return $ping;
    }
}
