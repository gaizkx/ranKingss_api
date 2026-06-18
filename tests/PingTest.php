<?php

declare(strict_types=1);

namespace App\Tests;

use App\Tests\ApiKeyAwareTestCase;
use App\Entity\User;

class PingTest extends ApiKeyAwareTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    public function testPingWithoutAuthReturnsStatusOk(): void
    {
        $response = self::createClient()->request('GET', '/ping', [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['status' => 'ok']);
    }

    public function testPingWithValidJwtReturnsUserId(): void
    {
        $client    = self::createClient();
        $container = self::getContainer();

        $password = 'test_password';
        $hashedPassword = '$2y$13$9Fs/hEWENck3e.7uSrXDUulUN55ORadXO01vTkYXGpfpQU1vf3MzS';

        $user = new User();
        $user->setAccountNumber('123456789012');
        $user->setPassword($hashedPassword);

        $container->get('doctrine')->getManager()->persist($user);
        $container->get('doctrine')->getManager()->flush();

        $response = $client->request('POST', '/auth', [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'account'  => '123456789012',
                'password' => $password,
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertArrayHasKey('token', $response->toArray());

        $response = $client->request('GET', '/ping', [
            'auth_bearer' => $response->toArray()['token'],
            'headers'     => ['Accept' => 'application/json'],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals([
            'status'  => 'ok',
            'user_id' => '123456789012',
        ]);
    }

    public function testPingWithInvalidJwtReturnsStatusOkWithoutUserId(): void
    {
        $response = self::createClient()->request('GET', '/ping', [
            'headers' => [
                'Authorization' => 'Bearer TEST_TOKEN_123',
                'Accept'        => 'application/json',
            ],
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertJsonEquals(['status' => 'ok']);
    }
}
