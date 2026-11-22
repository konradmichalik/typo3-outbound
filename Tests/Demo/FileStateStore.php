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

use KonradMichalik\Typo3Outbound\Resilience\StateStore;

/**
 * FileStateStore.
 *
 * Keeps circuit breaker and quota state across page loads of the demo.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class FileStateStore implements StateStore
{
    public function __construct(
        private string $directory,
    ) {
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }
    }

    public function get(string $key): ?array
    {
        $file = $this->file($key);
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (!is_array($data) || ($data['expires'] ?? 0) < time() || !is_array($data['value'] ?? null)) {
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, array $value, int $ttl): void
    {
        file_put_contents($this->file($key), json_encode(['value' => $value, 'expires' => time() + $ttl]));
    }

    public function delete(string $key): void
    {
        if (is_file($this->file($key))) {
            unlink($this->file($key));
        }
    }

    private function file(string $key): string
    {
        return $this->directory.'/'.sha1($key).'.json';
    }
}
