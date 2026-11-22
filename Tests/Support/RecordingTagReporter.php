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

namespace KonradMichalik\Typo3Outbound\Tests\Support;

use KonradMichalik\Typo3Outbound\Cache\CacheTagReporter;

/**
 * RecordingTagReporter.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class RecordingTagReporter implements CacheTagReporter
{
    /** @var list<array{tags: list<string>, lifetime: int}> */
    public array $reports = [];

    public function report(array $tags, int $lifetime): void
    {
        $this->reports[] = ['tags' => $tags, 'lifetime' => $lifetime];
    }
}
