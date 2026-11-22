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

use KonradMichalik\Typo3Outbound\Enum\PaginationStyle;
use KonradMichalik\Typo3Outbound\Format\PlainJson;
use KonradMichalik\Typo3Outbound\Http\{HostGuard, LinkHeader};
use KonradMichalik\Typo3Outbound\Metadata\{ConnectionMetadata, EndpointMetadata};
use KonradMichalik\Typo3Outbound\Support\DotPath;
use Psr\Log\LoggerInterface;

use function array_filter;
use function count;
use function is_array;
use function is_scalar;

/**
 * Paginator.
 *
 * Loads page after page lazily while the consumer iterates. Next-page links
 * taken from headers or documents go through the HostGuard like every other
 * URL, so a response can never redirect the client to a foreign host.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class Paginator
{
    public function __construct(
        private CallFactory $calls,
        private CacheLayer $layer,
        private ResultMapper $results,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $arguments
     *
     * @return \Generator<int, mixed>
     */
    public function iterate(EndpointMetadata $endpoint, ConnectionMetadata $connection, array $arguments): \Generator
    {
        $pagination = $endpoint->paginate ?? throw new \LogicException('Endpoint is not paginated.', 1794700150);
        $format = $connection->definition->format;
        $page = $pagination->firstPage;
        $offset = 0;
        $cursor = null;
        $url = null;

        for ($index = 0; $index < $pagination->maxPages; ++$index) {
            $extra = match ($pagination->style) {
                PaginationStyle::Page => [$pagination->pageParam => $page, $pagination->sizeParam => $pagination->size],
                PaginationStyle::Offset => [$pagination->pageParam => $offset, $pagination->sizeParam => $pagination->size],
                PaginationStyle::Cursor => array_filter([$pagination->cursorParam => $cursor, $pagination->sizeParam => $pagination->size], static fn(mixed $value): bool => null !== $value),
                PaginationStyle::LinkHeader, PaginationStyle::Format => null === $url ? [$pagination->sizeParam => $pagination->size] : [],
            };
            $execution = $this->calls->create($endpoint, $connection, $arguments, $extra, $url);
            $result = $this->layer->finish($this->layer->begin($execution));
            if (!$result->isSuccessful()) {
                $this->results->map($result, $execution);

                return;
            }

            $document = $this->results->decode($result, $execution);
            $data = DotPath::get($document, $endpoint->verb->unwrap);
            $items = null !== $pagination->itemsPath
                ? DotPath::get($data, $pagination->itemsPath)
                : ($format instanceof PlainJson ? $data : $format->collection($data));
            if (!is_array($items) || [] === $items) {
                return;
            }
            foreach ($items as $item) {
                yield $this->results->mapItem($item, $execution);
            }

            $count = count($items);
            switch ($pagination->style) {
                case PaginationStyle::Page:
                    if ($count < $pagination->size) {
                        return;
                    }
                    ++$page;
                    break;
                case PaginationStyle::Offset:
                    if ($count < $pagination->size) {
                        return;
                    }
                    $offset += $count;
                    break;
                case PaginationStyle::Cursor:
                    $previous = $cursor;
                    $cursor = DotPath::get($document, $pagination->cursorPath);
                    // A server echoing the cursor it was just given would repeat the same page until maxPages.
                    if (!is_scalar($cursor) || '' === (string) $cursor || $cursor === $previous) {
                        return;
                    }
                    break;
                case PaginationStyle::LinkHeader:
                case PaginationStyle::Format:
                    $next = PaginationStyle::LinkHeader === $pagination->style ? LinkHeader::next($result->header('link')) : $format->nextLink($document);
                    if (null === $next) {
                        return;
                    }
                    $nextUrl = HostGuard::absolute($next, $result->url);
                    if ($nextUrl === $result->url) {
                        return;
                    }
                    $url = $nextUrl;
                    break;
            }
        }

        $this->logger->notice('{endpoint} stopped paginating after maxPages={max}.', ['endpoint' => $endpoint->key, 'max' => $pagination->maxPages]);
    }
}
