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

use KonradMichalik\Typo3Outbound\Metadata\Naming;
use KonradMichalik\Typo3Outbound\Transport\{Call, Transport};
use LogicException;
use ReflectionClass;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_filter;
use function array_values;
use function count;
use function json_encode;
use function sprintf;

/**
 * MockTransport.
 *
 * Answers calls by endpoint and arguments instead of URLs, so changing a path
 * in an interface breaks no test. An unexpected call fails immediately; no test
 * can silently reach a real API.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class MockTransport implements Transport
{
    /** @var list<MockExpectation> */
    private array $expectations = [];

    /** @var list<Call> */
    private array $calls = [];

    /**
     * @param class-string         $interface
     * @param array<string, mixed> $arguments Subset of named arguments the call must match
     */
    public function on(string $interface, string $method, array $arguments = []): MockExpectation
    {
        $expectation = new MockExpectation(self::key($interface, $method), $arguments);
        $this->expectations[] = $expectation;

        return $expectation;
    }

    /**
     * Registers a response for a call that is not an endpoint, e.g. an OAuth token request.
     */
    public function onKey(string $key): MockExpectation
    {
        $expectation = new MockExpectation($key, []);
        $this->expectations[] = $expectation;

        return $expectation;
    }

    public function request(Call $call): ResponseInterface
    {
        $this->calls[] = $call;
        foreach ($this->expectations as $expectation) {
            if ($expectation->matches($call)) {
                $client = new MockHttpClient($expectation->next());

                return $client->request($call->request->method, $call->request->url, [
                    'headers' => $call->request->headers,
                    'body' => $call->request->body ?? '',
                ]);
            }
        }

        throw new LogicException(sprintf('Unexpected call to %s with %s.', $call->endpoint, json_encode($call->arguments)), 1794700170);
    }

    /**
     * @param class-string|null $interface
     *
     * @return list<Call>
     */
    public function calls(?string $interface = null, ?string $method = null): array
    {
        if (null === $interface || null === $method) {
            return $this->calls;
        }
        $key = self::key($interface, $method);

        return array_values(array_filter($this->calls, static fn (Call $call): bool => $call->endpoint === $key));
    }

    /**
     * @param class-string $interface
     */
    public function count(string $interface, string $method): int
    {
        return count($this->calls($interface, $method));
    }

    public function reset(): void
    {
        $this->expectations = [];
        $this->calls = [];
    }

    /**
     * @param class-string $interface
     */
    public static function key(string $interface, string $method): string
    {
        return Naming::endpointKey((new ReflectionClass($interface))->getShortName(), $method);
    }
}
