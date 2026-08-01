<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Configuration;
use CheckCommerce\Enums\PaymentType;
use CheckCommerce\Enums\TransactionStatus;
use CheckCommerce\Environment;
use CheckCommerce\Exception\InvalidArgumentException;
use CheckCommerce\Scope;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsToProduction(): void
    {
        $config = new Configuration(apiKey: 'key', merchantNumber: '1');

        self::assertSame(Environment::Production, $config->environment);
        self::assertSame('https://checkcommerce.com/api', $config->baseUrl);
    }

    public function testAcceptsEnvironmentStringsAndBaseUrlOverride(): void
    {
        $config = Configuration::fromArray([
            'api_key' => 'key',
            'merchant_number' => '1',
            'environment' => 'sandbox',
        ]);
        self::assertSame('https://sandbox.checkcommerce.com/api', $config->baseUrl);

        $config = Configuration::fromArray([
            'api_key' => 'key',
            'merchant_number' => '1',
            'base_url' => 'https://proxy.internal/api/',
        ]);
        self::assertSame('https://proxy.internal/api', $config->baseUrl);
    }

    public function testNormalizesScopes(): void
    {
        $config = new Configuration(
            apiKey: 'key',
            merchantNumber: '1',
            scopes: [Scope::Transactions, 'obp.hp'],
        );

        self::assertSame(['obp.tran', 'obp.hp'], $config->scopes);
    }

    public function testRejectsMissingCredentials(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Configuration(apiKey: '', merchantNumber: '1');
    }

    public function testRejectsUnknownEnvironment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Configuration(apiKey: 'key', merchantNumber: '1', environment: 'staging');
    }

    public function testRejectsUnknownOptionKeys(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Configuration::fromArray([
            'api_key' => 'key',
            'merchant_number' => '1',
            'apikey' => 'typo',
        ]);
    }

    public function testEnumFromApiHandlesNamesOrdinalsAndUnknowns(): void
    {
        self::assertSame(TransactionStatus::Processed, TransactionStatus::fromApi('Processed'));
        self::assertSame(TransactionStatus::Processed, TransactionStatus::fromApi(0));
        self::assertSame(TransactionStatus::Nsf, TransactionStatus::fromApi('NSF'));
        self::assertSame(TransactionStatus::Nsf, TransactionStatus::fromApi('nsf'));
        self::assertNull(TransactionStatus::fromApi('NotAThing'));
        self::assertNull(TransactionStatus::fromApi(999));
        self::assertSame(PaymentType::PaperCheck, PaymentType::fromApi(2));
    }
}
