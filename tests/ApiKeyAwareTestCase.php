<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;

abstract class ApiKeyAwareTestCase extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = false;

    protected static function createClient(array $kernelOptions = [], array $defaultOptions = []): Client
    {
        $defaultOptions['headers']['X-API-Key'] ??= 'test-api-key';

        return parent::createClient($kernelOptions, $defaultOptions);
    }
}
