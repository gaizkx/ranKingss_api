<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\PingProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/ping',
            provider: PingProvider::class,
            output: Ping::class,
            formats: ['json' => 'application/json'],
            openapi: new Operation(
                summary: 'Health check',
                description: 'Returns `ok`. If authenticated, also returns `user_id`.',
                tags: ['Health'],
                responses: [
                    200 => new Response(
                        description: 'Ping response',
                        content: new \ArrayObject([
                            'application/json' => [
                                'schema' => [
                                    'type'       => 'object',
                                    'properties' => [
                                        'status'  => ['type' => 'string',  'example' => 'ok'],
                                        'user_id' => ['type' => 'string',  'example' => '123456123456', 'nullable' => true],
                                    ],
                                ],
                            ],
                        ]),
                    ),
                ],
            ),
        ),
    ],
)]
class Ping
{
    public string $status = 'ok';

    public ?string $user_id = null;
}
