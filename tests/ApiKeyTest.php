<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;

class ApiKeyTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testRequestWithoutApiKeyReturns401(): void
    {
        self::createClient()->request('GET', '/ping', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['error' => 'Invalid or missing X-API-Key']);
    }

    public function testRequestWithWrongApiKeyReturns401(): void
    {
        self::createClient()->request('GET', '/ping', [
            'headers' => [
                'Accept'    => 'application/json',
                'X-API-Key' => 'totally-wrong-key',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
        $this->assertJsonContains(['error' => 'Invalid or missing X-API-Key']);
    }

    public function testRequestWithCorrectApiKeyAndPublicRouteReturns200(): void
    {
        self::createClient()->request('GET', '/ping', [
            'headers' => [
                'Accept'    => 'application/json',
                'X-API-Key' => 'test-api-key',
            ],
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testRequestWithCorrectApiKeyButNoJwtOnProtectedRouteReturns401(): void
    {
        self::createClient()->request('GET', '/employees', [
            'headers' => [
                'Accept'    => 'application/ld+json',
                'X-API-Key' => 'test-api-key',
            ],
        ]);

        $this->assertResponseStatusCodeSame(401);
    }
}
