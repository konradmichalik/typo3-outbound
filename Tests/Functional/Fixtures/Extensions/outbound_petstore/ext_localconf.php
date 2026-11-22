<?php

declare(strict_types=1);

use KonradMichalik\OutboundPetstore\Controller\PetsController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit;

ExtensionUtility::configurePlugin(
    'OutboundPetstore',
    'Pets',
    [PetsController::class => 'list, show, new, create, inventory'],
    [PetsController::class => 'new, create'],
);
