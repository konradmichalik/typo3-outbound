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

namespace KonradMichalik\Typo3Outbound\Value;

use InvalidArgumentException;

use function basename;
use function is_readable;
use function sprintf;

/**
 * FilePart.
 *
 * A file inside a #[Multipart] body, either from a path or from contents.
 *
 * @api
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class FilePart
{
    private function __construct(
        public ?string $path,
        public ?string $contents,
        public string $filename,
        public ?string $contentType,
    ) {}

    public static function fromPath(string $path, ?string $filename = null, ?string $contentType = null): self
    {
        if (!is_readable($path)) {
            throw new InvalidArgumentException(sprintf('File "%s" is not readable.', $path), 1794700041);
        }

        return new self($path, null, $filename ?? basename($path), $contentType);
    }

    public static function fromContents(string $contents, string $filename, ?string $contentType = null): self
    {
        return new self(null, $contents, $filename, $contentType);
    }
}
