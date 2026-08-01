<?php

declare(strict_types=1);

namespace CheckCommerce\Http;

use CheckCommerce\Configuration;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientInterface;

/**
 * Builds the default PSR-18 client when none is injected.
 *
 * Guzzle is preferred when installed so the configured timeouts apply;
 * otherwise any discoverable PSR-18 implementation is used.
 *
 * @internal
 */
final class HttpClientFactory
{
    public static function create(Configuration $config): ClientInterface
    {
        if (class_exists(\GuzzleHttp\Client::class)) {
            return new \GuzzleHttp\Client([
                'timeout' => $config->timeout,
                'connect_timeout' => $config->connectTimeout,
                'http_errors' => false,
                'allow_redirects' => false,
            ]);
        }

        return Psr18ClientDiscovery::find();
    }
}
