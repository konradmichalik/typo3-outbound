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

use Composer\Autoload;
use ShipMonk\ComposerDependencyAnalyser;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$rootPath = dirname(__DIR__, 2);

/** @var Autoload\ClassLoader $loader */
$loader = require $rootPath.'/vendor/autoload.php';
$loader->register();

$configuration = new ComposerDependencyAnalyser\Config\Configuration();
$configuration
    ->addPathToScan($rootPath.'/Configuration', false)
    ->addPathsToExclude([
        $rootPath.'/Tests/CGL',
        $rootPath.'/Tests/Functional/Fixtures',
    ])
    // Fixture files hold several classes each and are loaded with require_once, not autoloaded.
    ->ignoreUnknownClassesRegex('~^KonradMichalik\\\\Typo3Outbound\\\\Tests\\\\Unit\\\\Fixtures\\\\~')
    // The test helpers in Classes/Testing are public API for the tests of other extensions.
    ->ignoreErrorsOnPackageAndPath('phpunit/phpunit', $rootPath.'/Classes/Testing', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    // NoPrivateNetworkHttpClient needs IpUtils at runtime and only checks for it with class_exists().
    ->ignoreErrorsOnPackage('symfony/http-foundation', [ErrorType::UNUSED_DEPENDENCY])
;

return $configuration;
