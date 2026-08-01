<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Enums\PaymentType;
use CheckCommerce\Enums\TransactionType;
use CheckCommerce\Exception\InvalidArgumentException;
use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\TransactionResult;

/**
 * Submit payments and query their status.
 */
final class TransactionService extends AbstractService
{
    /**
     * Submits a transaction.
     *
     * ```php
     * $result = $client->transactions->create([
     *     'merchantNumber' => $client->config->merchantNumber,
     *     'transactionType' => TransactionType::Debit,
     *     'amount' => 42.50,
     *     'referenceNumber' => 'INV-1001',
     *     'consumerInfo' => [
     *         'name' => 'Jane Doe',
     *         'bankAccountNumber' => '1234567890',
     *         'bankRoutingNumber' => 121000248,
     *     ],
     * ]);
     * ```
     *
     * @param array<string, mixed> $request transaction fields (`merchantNumber`,
     *                                      `transactionType`, `amount`, `consumerInfo`, ...)
     * @param PaymentType|string $paymentType payment rail, defaults to ACH
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function create(
        array $request,
        PaymentType|string $paymentType = PaymentType::Ach,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        $response = $this->transport->request(
            'POST',
            '/transaction',
            jsonBody: [
                'paymentType' => $paymentType instanceof PaymentType ? $paymentType->value : $paymentType,
                'request' => $this->normalizeParams($request),
            ],
            options: RequestOptions::from($options),
        );

        return TransactionResult::fromArray($response->data);
    }

    /**
     * Submits a debit — collects funds from the consumer.
     *
     * @param array<string, mixed> $request
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function debit(
        array $request,
        PaymentType|string $paymentType = PaymentType::Ach,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        return $this->create(
            $request + ['transactionType' => TransactionType::Debit],
            $paymentType,
            $options,
        );
    }

    /**
     * Submits a credit — sends funds to the consumer.
     *
     * @param array<string, mixed> $request
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function credit(
        array $request,
        PaymentType|string $paymentType = PaymentType::Ach,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        return $this->create(
            $request + ['transactionType' => TransactionType::Credit],
            $paymentType,
            $options,
        );
    }

    /**
     * Refunds a settled transaction. Reference the original via
     * `originalTransaction` (`transactionId` or `referenceNumber`).
     *
     * @param array<string, mixed> $request
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function refund(
        array $request,
        PaymentType|string $paymentType = PaymentType::Ach,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        return $this->create(
            $request + ['transactionType' => TransactionType::Refund],
            $paymentType,
            $options,
        );
    }

    /**
     * Voids a transaction that has not yet been originated.
     *
     * @param array<string, mixed> $request
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function void(
        array $request,
        PaymentType|string $paymentType = PaymentType::Ach,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        return $this->create(
            $request + ['transactionType' => TransactionType::Void],
            $paymentType,
            $options,
        );
    }

    /**
     * Retrieves the current status of a transaction.
     *
     * Provide `transactionId`, `referenceNumber`, or both.
     *
     * @param int|null $transactionId id assigned by the API
     * @param string|null $referenceNumber merchant-assigned reference
     * @param PaymentType|string|null $requestType payment rail of the transaction
     * @param string|null $merchantNumber aggregators only: child merchant to act on behalf of
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function status(
        ?int $transactionId = null,
        ?string $referenceNumber = null,
        PaymentType|string|null $requestType = null,
        ?string $merchantNumber = null,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        $this->assertIdentifier($transactionId, $referenceNumber);

        $response = $this->transport->request(
            'GET',
            '/transaction/status',
            query: [
                'transactionId' => $transactionId,
                'referenceNumber' => $referenceNumber,
                'requestType' => $requestType,
                'merchantNumber' => $merchantNumber,
            ],
            options: RequestOptions::from($options),
        );

        return TransactionResult::fromArray($response->data);
    }

    /**
     * Retrieves the status of an ACH authentication transaction.
     *
     * Provide `transactionId`, `referenceNumber`, or both.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function authStatus(
        ?int $transactionId = null,
        ?string $referenceNumber = null,
        ?string $merchantNumber = null,
        RequestOptions|array|null $options = null,
    ): TransactionResult {
        $this->assertIdentifier($transactionId, $referenceNumber);

        $response = $this->transport->request(
            'GET',
            '/transaction/authstatus',
            query: [
                'transactionId' => $transactionId,
                'referenceNumber' => $referenceNumber,
                'merchantNumber' => $merchantNumber,
            ],
            options: RequestOptions::from($options),
        );

        return TransactionResult::fromArray($response->data);
    }

    private function assertIdentifier(?int $transactionId, ?string $referenceNumber): void
    {
        if (null === $transactionId && (null === $referenceNumber || '' === trim($referenceNumber))) {
            throw new InvalidArgumentException('Provide a transactionId or a referenceNumber.');
        }
    }
}
