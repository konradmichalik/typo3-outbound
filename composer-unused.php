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

use ComposerUnused\ComposerUnused\Configuration\{Configuration, NamedFilter};

return static function (Configuration $config): Configuration {
    // NoPrivateNetworkHttpClient needs symfony/http-foundation (IpUtils) at runtime without a direct reference.
    return $config->addNamedFilter(NamedFilter::fromString('symfony/http-foundation'));
};
