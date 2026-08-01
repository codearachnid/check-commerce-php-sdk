<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\HostedPageLink;

/**
 * Generate hosted payment page links.
 */
final class HostedPageService extends AbstractService
{
    /**
     * Generates a hosted payment page link.
     *
     * ```php
     * $link = $client->hostedPages->createLink([
     *     'customer' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
     *     'order' => ['total' => 99.95, 'returnURL' => 'https://example.com/thanks'],
     * ]);
     * $url = $link->url;
     * ```
     *
     * @param array<string, mixed> $params `business`, `customer`, `order`,
     *                                     `orderItems`, `discount`
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function createLink(array $params, RequestOptions|array|null $options = null): HostedPageLink
    {
        $response = $this->transport->request(
            'POST',
            '/hostedpage/link',
            jsonBody: $this->normalizeParams($params),
            options: RequestOptions::from($options),
        );

        return HostedPageLink::fromArray($response->data);
    }
}
