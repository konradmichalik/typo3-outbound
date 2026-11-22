<?php

declare(strict_types=1);

use KonradMichalik\Typo3Outbound\DependencyInjection\OutboundServices;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $configurator, ContainerBuilder $builder): void {
    OutboundServices::discover($builder, __DIR__.'/../Classes/Api');
};
