<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\State\RegisterProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/register',
            processor: RegisterProcessor::class,
            formats: ['json' => 'application/json'],
            openapi: new Operation(
                summary: 'Register a new user',
                description: 'Creates a new user with the given account number and password.',
                tags: ['Auth'],
                responses: [
                    204 => new Response(
                        description: 'User created successfully',
                    ),
                ],
            ),
            output: false,
        ),
    ],
)]
class Register
{
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 12)]
    public string $accountNumber;

    #[Assert\NotBlank]
    #[Assert\Length(min: 6)]
    public string $password;
}
