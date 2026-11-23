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

namespace KonradMichalik\Typo3Outbound\Testing;

use KonradMichalik\Typo3Outbound\Transport\Call;
use RuntimeException;
use Symfony\Component\HttpClient\Response\MockResponse;

use function array_key_exists;
use function array_shift;
use function count;
use function file_get_contents;
use function is_string;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * MockExpectation.
 *
 * Responses are returned in the order they were added; the last one repeats.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MockExpectation
{
    /** @var list<MockResponse> */
    private array $responses = [];

    /**
     * @param array<string, mixed> $arguments
     */
    public function __construct(
        private readonly string $endpoint,
        private readonly array $arguments,
    ) {}

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function respondWith(int $status = 200, string $body = '', array $headers = []): self
    {
        $this->responses[] = new MockResponse($body, ['http_code' => $status, 'response_headers' => $headers]);

        return $this;
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function respondWithJson(mixed $data, int $status = 200, array $headers = []): self
    {
        return $this->respondWith($status, json_encode($data, JSON_THROW_ON_ERROR), ['content-type' => 'application/json', ...$headers]);
    }

    /**
     * @param array<string, string|list<string>> $headers
     */
    public function respondWithFixture(string $path, int $status = 200, array $headers = []): self
    {
        $body = file_get_contents($path);
        if (!is_string($body)) {
            throw new RuntimeException(sprintf('Fixture "%s" is not readable.', $path), 1794700171);
        }

        return $this->respondWith($status, $body, ['content-type' => 'application/json', ...$headers]);
    }

    public function failWith(string $message = 'Connection refused'): self
    {
        $this->responses[] = new MockResponse('', ['error' => $message]);

        return $this;
    }

    /**
     * @internal
     */
    public function matches(Call $call): bool
    {
        if ($call->endpoint !== $this->endpoint) {
            return false;
        }
        foreach ($this->arguments as $name => $value) {
            if (!array_key_exists($name, $call->arguments) || $call->arguments[$name] != $value) {
                return false;
            }
        }

        return true;
    }

    /**
     * @internal
     */
    public function next(): MockResponse
    {
        if ([] === $this->responses) {
            throw new RuntimeException(sprintf('No response configured for %s.', $this->endpoint), 1794700172);
        }

        return count($this->responses) > 1 ? array_shift($this->responses) : $this->responses[0];
    }
}
