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

namespace KonradMichalik\Typo3Outbound\Configuration;

use TYPO3\CMS\Core\Cache\Backend\{SimpleFileBackend, Typo3DatabaseBackend};
use TYPO3\CMS\Core\Cache\Frontend\{PhpFrontend, VariableFrontend};

/**
 * Configuration.
 *
 * Registers the caches of the extension; called from ext_localconf.php. Every
 * cache can be reconfigured, e.g. to Redis, in config/system/additional.php.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class Configuration
{
    public static function registerCaches(): void
    {
        $caches = &$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'];
        $caches['outbound'] ??= ['frontend' => VariableFrontend::class, 'backend' => Typo3DatabaseBackend::class, 'groups' => ['pages', 'all']];
        $caches['outbound_state'] ??= ['frontend' => VariableFrontend::class, 'backend' => Typo3DatabaseBackend::class, 'groups' => ['system']];
        $caches['outbound_proxies'] ??= ['frontend' => PhpFrontend::class, 'backend' => SimpleFileBackend::class, 'groups' => ['system']];
    }
}
