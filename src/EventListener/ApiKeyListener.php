<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
final class ApiKeyListener
{
    public function __construct(private readonly string $apiKey) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $provided = $event->getRequest()->headers->get('X-API-Key');

        if ($provided === null || !hash_equals($this->apiKey, $provided)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid or missing X-API-Key'],
                JsonResponse::HTTP_UNAUTHORIZED,
            ));
            $event->stopPropagation();
        }
    }
}
