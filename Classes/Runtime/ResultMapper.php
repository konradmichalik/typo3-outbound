<?php

declare(strict_types=1);

/*
 * This file is part of the "typo3_outbound" TYPO3 CMS extension.
 *
 * (c) 2026 Konrad Michalik <hej@konradmichalik.dev>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KonradMichalik\Typo3Outbound\Runtime;

use JsonException;
use KonradMichalik\Typo3Outbound\Exception\{ApiConflict, ApiException, ApiMappingFailed, ApiNotFound, ApiRateLimited, ApiUnauthorized, ApiUnavailable, ApiValidationFailed};
use KonradMichalik\Typo3Outbound\Format\{ErrorDocument, PlainJson};
use KonradMichalik\Typo3Outbound\Metadata\ReturnKind;
use KonradMichalik\Typo3Outbound\Resilience\RetryPolicy;
use KonradMichalik\Typo3Outbound\Support\DotPath;
use KonradMichalik\Typo3Outbound\Value\RawResponse;
use Psr\Log\LoggerInterface;

use function feof;
use function fread;
use function in_array;
use function is_resource;
use function json_decode;
use function method_exists;
use function preg_match;
use function preg_replace;
use function sprintf;
use function str_contains;
use function strlen;
use function strtolower;
use function substr;
use function trim;

use const JSON_BIGINT_AS_STRING;
use const JSON_THROW_ON_ERROR;

/**
 * ResultMapper.
 *
 * Turns a result into the declared return value: #[OnStatus] overrides, typed
 * exceptions for error statuses, decoding with big integers as strings,
 * unwrapping, the response format and finally the mapper.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ResultMapper
{
    public function __construct(
        private \KonradMichalik\Typo3Outbound\Mapping\ResponseMapper $mapper,
        private LoggerInterface $logger,
    ) {}

    public function map(Result $result, Execution $execution): mixed
    {
        $endpoint = $execution->endpoint;
        if (!$result->isSuccessful()) {
            foreach ($endpoint->onStatus as $onStatus) {
                if ($onStatus->status === $result->status) {
                    return $onStatus->returns;
                }
            }

            throw $this->error($result, $execution);
        }

        return match ($endpoint->returnType->kind) {
            ReturnKind::Void => null,
            ReturnKind::Raw => $this->raw($result),
            default => $this->mapDocument($this->decode($result, $execution), $execution),
        };
    }

    public function decode(Result $result, Execution $execution): mixed
    {
        if ('' === trim($result->body)) {
            return null;
        }
        if ('string' === $execution->endpoint->returnType->signature && !self::isJson($result)) {
            return $result->body;
        }

        try {
            return json_decode($result->body, true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ApiMappingFailed(sprintf('%s: the response is not valid JSON.', $execution->endpoint->key), 1794700140, $execution->endpoint->key, $result->status, null, $exception);
        }
    }

    public function mapDocument(mixed $document, Execution $execution): mixed
    {
        $endpoint = $execution->endpoint;
        $format = $execution->connection->definition->format;
        $signature = $endpoint->returnType->signature;
        $data = DotPath::get($document, $endpoint->verb->unwrap);
        if (!$format instanceof PlainJson) {
            $data = self::isListSignature($signature) ? $format->collection($data) : $format->item($data);
        }
        if (null === $data && $endpoint->returnType->nullable) {
            return null;
        }

        return 'mixed' === $signature ? $data : $this->mapper->map($signature, $data, $execution->connection, $endpoint->mapKeys, $endpoint->key);
    }

    /**
     * Maps one item of a paginated response; items are already normalized by the format.
     */
    public function mapItem(mixed $item, Execution $execution): mixed
    {
        $endpoint = $execution->endpoint;

        return 'mixed' === $endpoint->returnType->signature ? $item : $this->mapper->map($endpoint->returnType->signature, $item, $execution->connection, $endpoint->mapKeys, $endpoint->key);
    }

    public static function isListSignature(string $signature): bool
    {
        return 1 === preg_match('/^(\?)?(list|non-empty-list|array|non-empty-array|iterable)\s*</', $signature);
    }

    private function error(Result $result, Execution $execution): ApiException
    {
        $key = $execution->endpoint->key;
        $status = $result->status;
        $document = null;
        try {
            $document = '' === trim($result->body) ? null : json_decode($result->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
        }
        $error = $execution->connection->definition->format->error($status, $document) ?? new ErrorDocument();
        $requestId = $result->header('x-request-id') ?? $result->header('request-id') ?? $result->header('x-correlation-id');
        $message = sprintf('%s answered %d%s', $key, $status, null !== $error->title ? ': '.self::excerpt($error->title) : '');
        if (null !== $error->detail) {
            $message .= ' ('.self::excerpt($error->detail).')';
        }
        $this->logger->debug('{endpoint} error body: {body}', ['endpoint' => $key, 'body' => substr($result->body, 0, 500)]);

        return match (true) {
            in_array($status, [400, 422], true) => new ApiValidationFailed($message, 1794700141, $key, $status, $requestId, $error->fieldErrors),
            in_array($status, [401, 403], true) => new ApiUnauthorized($message, 1794700142, $key, $status, $requestId),
            404 === $status, 410 === $status => new ApiNotFound($message, 1794700143, $key, $status, $requestId),
            in_array($status, [409, 412], true) => new ApiConflict($message, 1794700144, $key, $status, $requestId),
            429 === $status => new ApiRateLimited($message, 1794700145, $key, $status, $requestId, self::retryAfter($result)),
            $status >= 500 => new ApiUnavailable($message, 1794700146, $key, $status, $requestId),
            default => new ApiValidationFailed($message, 1794700147, $key, $status, $requestId, $error->fieldErrors),
        };
    }

    /**
     * Text from an error document is controlled by the API: keep it short and on one line.
     */
    private static function excerpt(string $text): string
    {
        $text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text));

        return strlen($text) > 200 ? substr($text, 0, 200).'...' : $text;
    }

    private function raw(Result $result): RawResponse
    {
        $response = $result->response;

        return new RawResponse(
            $result->status,
            $result->headers,
            static fn (): string => null !== $response ? $response->getContent(false) : $result->body,
            static function () use ($response, $result): iterable {
                if (null === $response) {
                    yield $result->body;

                    return;
                }
                $stream = method_exists($response, 'toStream') ? $response->toStream(false) : null;
                if (!is_resource($stream)) {
                    yield $response->getContent(false);

                    return;
                }
                while (!feof($stream)) {
                    $chunk = fread($stream, 65_536);
                    if (false === $chunk || '' === $chunk) {
                        break;
                    }
                    yield $chunk;
                }
            },
        );
    }

    private static function isJson(Result $result): bool
    {
        $type = strtolower($result->header('content-type') ?? '');

        return '' === $type || str_contains($type, 'json');
    }

    private static function retryAfter(Result $result): ?int
    {
        $header = $result->header('retry-after');
        $seconds = null !== $header ? RetryPolicy::parseRetryAfter($header) : null;

        return null !== $seconds ? (int) $seconds : null;
    }
}
