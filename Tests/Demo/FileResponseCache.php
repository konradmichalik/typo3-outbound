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

namespace KonradMichalik\Typo3Outbound\Tests\Demo;

use KonradMichalik\Typo3Outbound\Cache\{CacheEntry, ResponseCache};

use function is_array;

/**
 * FileResponseCache.
 *
 * JSON files in a temp directory, so the demo keeps its cache across page loads.
 * Inside TYPO3 the caching framework takes this role.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class FileResponseCache implements ResponseCache
{
    public function __construct(
        private string $directory,
    ) {
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }
    }

    public function get(string $key): ?CacheEntry
    {
        $file = $this->directory.'/'.$key.'.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) && is_array($data['entry'] ?? null) ? CacheEntry::fromArray($data['entry']) : null;
    }

    public function set(string $key, CacheEntry $entry, int $lifetime, array $tags): void
    {
        file_put_contents($this->directory.'/'.$key.'.json', json_encode(['entry' => $entry->toArray(), 'tags' => $tags]));
    }

    public function flushTags(array $tags): void
    {
        foreach (glob($this->directory.'/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && [] !== array_intersect((array) ($data['tags'] ?? []), $tags)) {
                unlink($file);
            }
        }
    }
}
