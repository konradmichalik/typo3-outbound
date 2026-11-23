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

namespace KonradMichalik\Typo3Outbound\Middleware;

use KonradMichalik\Typo3Outbound\Context\RequestContext;
use Psr\Http\Message\{ResponseInterface, ServerRequestInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};

/**
 * RequestContextMiddleware.
 *
 * Makes the current request, with its site and language, available to API calls
 * in frontend and backend. Registered right after site resolution.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class RequestContextMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RequestContext $context,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $previous = $this->context->request();
        $this->context->setRequest($request);
        try {
            return $handler->handle($request);
        } finally {
            $this->context->setRequest($previous);
        }
    }
}
