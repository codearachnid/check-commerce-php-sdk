<?php

declare(strict_types=1);

namespace CheckCommerce\Tests;

use CheckCommerce\Auth\Authenticator;
use CheckCommerce\Auth\InMemoryTokenStore;
use CheckCommerce\CheckCommerceClient;
use CheckCommerce\Configuration;
use CheckCommerce\Environment;
use CheckCommerce\Http\HttpTransport;
use CheckCommerce\Tests\Support\FakeHttpClient;
use GuzzleHttp\Psr7\HttpFactory;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected FakeHttpClient $http;

    /**
     * @param array<string, mixed> $overrides Configuration::fromArray() options
     */
    protected function client(array $overrides = []): CheckCommerceClient
    {
        $this->http = new FakeHttpClient();

        return new CheckCommerceClient(
            $this->configuration($overrides),
            $this->http,
            new HttpFactory(),
            new HttpFactory(),
        );
    }

    /**
     * Builds a transport wired to the fake client with retry delays disabled.
     *
     * @param array<string, mixed> $overrides
     */
    protected function transport(array $overrides = []): HttpTransport
    {
        $this->http = new FakeHttpClient();
        $config = $this->configuration($overrides);

        $transport = new HttpTransport($config, $this->http, new HttpFactory(), new HttpFactory());
        $transport->setAuthenticator(new Authenticator($transport, $config, new InMemoryTokenStore()));
        $transport->setSleeper(static function (int $milliseconds): void {
        });

        return $transport;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    protected function configuration(array $overrides = []): Configuration
    {
        return Configuration::fromArray($overrides + [
            'api_key' => 'test-api-key',
            'merchant_number' => '999997',
            'environment' => Environment::Sandbox,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeBody(\Psr\Http\Message\RequestInterface $request): array
    {
        $request->getBody()->rewind();

        return json_decode((string) $request->getBody(), true, 512, \JSON_THROW_ON_ERROR);
    }
}
