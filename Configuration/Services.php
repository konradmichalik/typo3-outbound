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

use KonradMichalik\Outbound\DependencyInjection\OutboundCompilerPass;
use KonradMichalik\Outbound\Outbound;
use KonradMichalik\Outbound\Testing\SwitchableTransport;
use KonradMichalik\Outbound\Transport\Transport;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Reference};
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Core\Environment;

return static function (ContainerConfigurator $configurator, ContainerBuilder $containerBuilder): void {
    $containerBuilder->addCompilerPass(new OutboundCompilerPass());

    // Services.php is loaded before Services.yaml, whose explicit definitions then override these.
    // The location of php-outbound differs between a Composer installation and the vendor bundle
    // of a classic mode installation, so it is taken from the autoloader.
    $directory = dirname((string) (new ReflectionClass(Outbound::class))->getFileName());

    $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->private()
        ->load('KonradMichalik\\Outbound\\', $directory.'/*')
        ->exclude([
            $directory.'/Attribute/*',
            $directory.'/Codegen/*',
            $directory.'/Enum/*',
            $directory.'/Exception/*',
            $directory.'/Format/*',
            $directory.'/Metadata/{ConnectionMetadata,EndpointMetadata,ParameterMetadata,ReturnType,Naming,TypeResolver,MetadataFactory}.php',
            $directory.'/Value/*',
            $directory.'/Outbound.php',
            $directory.'/Testing/*',
            $directory.'/Http/{RequestSpec,QuerySerializer,HostGuard,LinkHeader}.php',
            $directory.'/Cache/{CacheEntry,CacheKey,CacheTags,CacheLockHandle,InMemoryCacheLock,InMemoryResponseCache,NullCacheTagReporter,ResponseCacheInvalidator}.php',
            $directory.'/Resilience/{InMemoryStateStore,RetryPolicy}.php',
            $directory.'/Runtime/{Execution,Flight,Result,Pending,EtagMemory}.php',
            $directory.'/Transport/{Call,TransportOptions}.php',
            $directory.'/Auth/Credentials.php',
            $directory.'/Context/{SapiExecutionContext,StaticCallContext}.php',
            $directory.'/Support/{DotPath,Redactor}.php',
            $directory.'/Mapping/Normalizer.php',
        ]);

    // Lets OutboundTestTrait reach frontend subrequests, which boot their own container.
    if (Environment::getContext()->isTesting()) {
        $configurator->services()->set(SwitchableTransport::class)->decorate(Transport::class)->args([new Reference('.inner')]);
    }
};
