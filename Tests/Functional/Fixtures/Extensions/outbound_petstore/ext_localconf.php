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

use KonradMichalik\OutboundPetstore\Controller\PetsController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit;

ExtensionUtility::configurePlugin(
    'OutboundPetstore',
    'Pets',
    [PetsController::class => 'list, show, new, create, inventory'],
    [PetsController::class => 'new, create'],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);
