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

namespace KonradMichalik\Typo3Outbound\Tests\Unit;

use KonradMichalik\Typo3Outbound\Context\RequestContext;
use KonradMichalik\Typo3Outbound\Middleware\RequestContextMiddleware;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use TYPO3\CMS\Core\Http\{Response, ServerRequest};
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * RequestContextMiddlewareTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(RequestContextMiddleware::class)]
final class RequestContextMiddlewareTest extends TestCase
{
    #[Test]
    public function theRequestIsAvailableWhileTheHandlerRunsAndTheResponseIsPassedThrough(): void
    {
        $context = $this->context();
        $request = new ServerRequest('https://localhost/');
        $response = new Response();
        $seen = null;
        $handler = $this->handler(static function () use ($context, &$seen, $response): ResponseInterface {
            $seen = $context->request();

            return $response;
        });

        $result = (new RequestContextMiddleware($context))->process($request, $handler);

        self::assertSame($request, $seen);
        self::assertSame($response, $result);
    }

    #[Test]
    public function thePreviousRequestIsRestoredAfterwards(): void
    {
        $context = $this->context();
        $outer = new ServerRequest('https://localhost/outer');
        $context->setRequest($outer);

        (new RequestContextMiddleware($context))->process(new ServerRequest('https://localhost/inner'), $this->handler(static fn (): ResponseInterface => new Response()));

        self::assertSame($outer, $context->request());
    }

    #[Test]
    public function thePreviousRequestIsRestoredWhenTheHandlerThrows(): void
    {
        $context = $this->context();
        $handler = $this->handler(static fn (): never => throw new LogicException('boom', 4078844484));

        try {
            (new RequestContextMiddleware($context))->process(new ServerRequest('https://localhost/'), $handler);
            self::fail('The exception has to propagate.');
        } catch (LogicException) {
            self::assertNull($context->request());
        }
    }

    private function context(): RequestContext
    {
        return new RequestContext((new ReflectionClass(SiteFinder::class))->newInstanceWithoutConstructor());
    }

    /**
     * @param callable(): ResponseInterface $callback
     */
    private function handler(callable $callback): RequestHandlerInterface
    {
        return new class($callback) implements RequestHandlerInterface {
            /**
             * @param callable(): ResponseInterface $callback
             */
            public function __construct(private readonly mixed $callback) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->callback)();
            }
        };
    }
}
