<?php

declare(strict_types=1);

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model;
use App\State\RegisterProcessor;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new Post(
            uriTemplate: '/register',
            processor: RegisterProcessor::class,
            formats: ['json' => 'application/json'],
            openapi: new Model\Operation(
                summary: 'Register a new user',
                description: 'Creates a new user with the given account number and password.',
                tags: ['Auth'],
                responses: [
                    204 => new Model\Response(
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
