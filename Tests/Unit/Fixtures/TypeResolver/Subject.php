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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\TypeResolver;

use Vendor\Other\Dto as Alias;

/**
 * Its imports are what TypeResolverTest resolves against, e.g. {@see Alias}.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
interface Subject {}
