<?php

declare(strict_types=1);

namespace CheckCommerce\Service;

use CheckCommerce\Enums\FileDelimiter;
use CheckCommerce\Exception\InvalidArgumentException;
use CheckCommerce\Http\RequestOptions;
use CheckCommerce\Resources\BatchResult;

/**
 * Submit transactions in bulk, as JSON payloads or file uploads.
 */
final class BatchService extends AbstractService
{
    /**
     * Submits a batch of transactions.
     *
     * @param list<array<string, mixed>> $transactions batched transaction records
     * @param bool $isAuthFile whether the batch contains authorization transactions
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function submit(
        array $transactions,
        bool $isAuthFile = false,
        RequestOptions|array|null $options = null,
    ): BatchResult {
        if ([] === $transactions) {
            throw new InvalidArgumentException('A batch requires at least one transaction.');
        }

        $response = $this->transport->request(
            'POST',
            '/transaction/batch',
            jsonBody: [
                'transactions' => $this->normalizeParams($transactions),
                'isAuthFile' => $isAuthFile,
            ],
            options: RequestOptions::from($options),
        );

        return BatchResult::fromArray($response->data);
    }

    /**
     * Retrieves the processing status of a batch.
     *
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function status(int $batchId, RequestOptions|array|null $options = null): BatchResult
    {
        $response = $this->transport->request(
            'GET',
            '/transaction/batch',
            query: ['batchId' => $batchId],
            options: RequestOptions::from($options),
        );

        return BatchResult::fromArray($response->data);
    }

    /**
     * Uploads a batch transaction file from a string.
     *
     * @param string $contents raw file contents
     * @param string $filename original file name, its extension identifies the format
     * @param FileDelimiter|string|null $delimiter required for `txt` and `csv` files
     * @param bool $isAuthFile whether the file contains authorization transactions
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function upload(
        string $contents,
        string $filename,
        FileDelimiter|string|null $delimiter = null,
        bool $isAuthFile = false,
        RequestOptions|array|null $options = null,
    ): BatchResult {
        if ('' === $contents) {
            throw new InvalidArgumentException('The batch file contents are empty.');
        }

        $fields = ['isAuthFile' => $isAuthFile ? 'true' : 'false'];

        if (null !== $delimiter) {
            $fields['delimiter'] = $delimiter instanceof FileDelimiter ? $delimiter->value : $delimiter;
        }

        $response = $this->transport->upload(
            '/transaction/batch/upload',
            fields: $fields,
            files: [
                'file' => [
                    'filename' => $filename,
                    'contents' => $contents,
                ],
            ],
            options: RequestOptions::from($options),
        );

        return BatchResult::fromArray($response->data);
    }

    /**
     * Uploads a batch transaction file from disk.
     *
     * @param FileDelimiter|string|null $delimiter required for `txt` and `csv` files
     * @param RequestOptions|array<string, mixed>|null $options
     */
    public function uploadFile(
        string $path,
        FileDelimiter|string|null $delimiter = null,
        bool $isAuthFile = false,
        RequestOptions|array|null $options = null,
    ): BatchResult {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(\sprintf('Batch file "%s" does not exist or is not readable.', $path));
        }

        $contents = file_get_contents($path);

        if (false === $contents) {
            throw new InvalidArgumentException(\sprintf('Unable to read batch file "%s".', $path));
        }

        return $this->upload($contents, basename($path), $delimiter, $isAuthFile, $options);
    }
}
