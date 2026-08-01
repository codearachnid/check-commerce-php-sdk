<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\BoardingResult;

/**
 * Board new merchants under an aggregator account.
 */
final class BoardingService extends AbstractService
{
    /**
     * Submits merchants for boarding.
     *
     * @param array<string, mixed> $params boarding request, typically
     *                                     `{'merchants': [...]}` per the API reference
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function board(array $params, RequestOptions|array|null $options = null): BoardingResult
    {
        $response = $this->transport->request(
            'POST',
            '/board',
            jsonBody: $this->normalizeParams($params),
            options: RequestOptions::from($options),
        );

        return BoardingResult::fromArray($response->data);
    }
}
