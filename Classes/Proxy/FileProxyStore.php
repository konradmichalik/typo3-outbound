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

namespace KonradMichalik\Typo3Outbound\Proxy;

use function file_put_contents;
use function fileperms;
use function is_dir;
use function is_file;
use function mkdir;
use function rename;
use function sprintf;
use function uniqid;

/**
 * FileProxyStore.
 *
 * Writes proxies as PHP files into a directory, atomically via rename.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class FileProxyStore implements ProxyStore
{
    public function __construct(
        private string $directory,
    ) {}

    public function has(string $identifier): bool
    {
        return is_file($this->path($identifier));
    }

    public function save(string $identifier, string $code): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0o700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException(sprintf('Cannot create proxy directory "%s".', $this->directory), 1794700163);
        }
        $temporary = $this->path($identifier).'.'.uniqid('', true);
        file_put_contents($temporary, $code);
        rename($temporary, $this->path($identifier));
    }

    public function load(string $identifier): void
    {
        // The files are executed. A directory other users can write to would let them plant code.
        $permissions = fileperms($this->directory);
        if (false === $permissions || 0 !== ($permissions & 0o022)) {
            throw new \RuntimeException(sprintf('Proxy directory "%s" is writable by group or others; refusing to load code from it.', $this->directory), 1794700164);
        }

        require_once $this->path($identifier);
    }

    private function path(string $identifier): string
    {
        return $this->directory.'/'.$identifier.'.php';
    }
}
