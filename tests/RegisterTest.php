<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\ApiKeyAwareTestCase;

class RegisterTest extends ApiKeyAwareTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testRegisterCreatesUserAndCanLogin(): void
    {
        $client = self::createClient();

        $response = $client->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json'    => [
                'accountNumber' => '123456789012',
                'password'      => 'test_password',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        $response = $client->request('GET', '/ping', [
            'headers' => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['status' => 'ok']);

        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'account'  => '123456789012',
                'password' => 'test_password',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());
    }

    public function testRegisterDuplicateAccountNumberReturnsConflict(): void
    {
        $client = self::createClient();

        $client->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json'    => [
                'accountNumber' => '999999999999',
                'password'      => 'test_password',
            ],
        ]);

        $this->assertResponseStatusCodeSame(204);

        $response = $client->request('POST', '/register', [
            'headers' => ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            'json'    => [
                'accountNumber' => '999999999999',
                'password'      => 'test_password',
            ],
        ]);

        $this->assertResponseStatusCodeSame(409);
    }
}
