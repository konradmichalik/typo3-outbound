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

use KonradMichalik\Typo3Outbound\DependencyInjection\OutboundCompilerPass;
use KonradMichalik\Typo3Outbound\Testing\SwitchableTransport;
use KonradMichalik\Typo3Outbound\Transport\Transport;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Reference};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Core\Environment;

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $containerBuilder->addCompilerPass(new OutboundCompilerPass());

    // Lets OutboundTestTrait reach frontend subrequests, which boot their own container.
    if (Environment::getContext()->isTesting()) {
        $configurator->services()->set(SwitchableTransport::class)->decorate(Transport::class)->args([new Reference('.inner')]);
    }
};
