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
 * InvalidatesCache.
 *
 * Flushes the given tags after a 2xx response of a write endpoint, never after
 * errors. Placeholders work like in #[Cache], e.g. "crm_contact_{id}". With a
 * Deferred return type the flush happens when get() is called.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final readonly class InvalidatesCache
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public array $tags,
    ) {}
}
