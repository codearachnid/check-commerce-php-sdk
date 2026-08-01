<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\CheckCommerceClient;
use CheckCommerce\Environment;
use CheckCommerce\Exception\InvalidArgumentException;

final class FromEnvTest extends TestCase
{
    private const VARIABLES = [
        'CHECK_COMMERCE_API_KEY',
        'CHECK_COMMERCE_MERCHANT_NUMBER',
        'CHECK_COMMERCE_ENVIRONMENT',
    ];

    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $variable) {
            unset($_ENV[$variable], $_SERVER[$variable]);
            putenv($variable);
        }
    }

    public function testBuildsClientFromEnvironmentVariables(): void
    {
        $_ENV['CHECK_COMMERCE_API_KEY'] = 'env-key';
        $_ENV['CHECK_COMMERCE_MERCHANT_NUMBER'] = '999997';
        $_ENV['CHECK_COMMERCE_ENVIRONMENT'] = 'sandbox';

        $client = CheckCommerceClient::fromEnv();

        self::assertSame('env-key', $client->config->apiKey);
        self::assertSame('999997', $client->config->merchantNumber);
        self::assertSame(Environment::Sandbox, $client->config->environment);
    }

    public function testDefaultsToProductionWhenEnvironmentUnset(): void
    {
        $_ENV['CHECK_COMMERCE_API_KEY'] = 'env-key';
        $_ENV['CHECK_COMMERCE_MERCHANT_NUMBER'] = '999997';

        $client = CheckCommerceClient::fromEnv();

        self::assertSame(Environment::Production, $client->config->environment);
    }

    public function testReadsFromGetenvWhenSuperglobalsAreUnset(): void
    {
        putenv('CHECK_COMMERCE_API_KEY=getenv-key');
        putenv('CHECK_COMMERCE_MERCHANT_NUMBER=999997');

        $client = CheckCommerceClient::fromEnv();

        self::assertSame('getenv-key', $client->config->apiKey);
    }

    public function testOverridesTakePrecedenceOverEnvironment(): void
    {
        $_ENV['CHECK_COMMERCE_API_KEY'] = 'env-key';
        $_ENV['CHECK_COMMERCE_MERCHANT_NUMBER'] = '999997';
        $_ENV['CHECK_COMMERCE_ENVIRONMENT'] = 'production';

        $client = CheckCommerceClient::fromEnv([
            'environment' => 'sandbox',
            'max_retries' => 5,
        ]);

        self::assertSame(Environment::Sandbox, $client->config->environment);
        self::assertSame(5, $client->config->maxRetries);
        self::assertSame('env-key', $client->config->apiKey);
    }

    public function testNamesTheMissingApiKeyVariable(): void
    {
        $_ENV['CHECK_COMMERCE_MERCHANT_NUMBER'] = '999997';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CHECK_COMMERCE_API_KEY');

        CheckCommerceClient::fromEnv();
    }

    public function testNamesTheMissingMerchantNumberVariable(): void
    {
        $_ENV['CHECK_COMMERCE_API_KEY'] = 'env-key';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('CHECK_COMMERCE_MERCHANT_NUMBER');

        CheckCommerceClient::fromEnv();
    }
}
