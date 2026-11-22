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

namespace KonradMichalik\Typo3Outbound\Attribute;

/**
 * Cache.
 *
 * Caches the decoded response. Tags may contain placeholders of arguments, e.g.
 * "crm_contact_{id}". With `staleOnError`, an expired entry is still served for
 * that many seconds when the API fails.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class Cache
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public int $lifetime,
        public array $tags = [],
        public int $staleOnError = 0,
    ) {}
}
