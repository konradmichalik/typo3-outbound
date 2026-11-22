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

namespace KonradMichalik\Typo3Outbound\Proxy;

use KonradMichalik\Typo3Outbound\Runtime\EndpointInvoker;

use function class_exists;
use function filemtime;
use function filesize;
use function is_string;
use function sha1;
use function substr;

/**
 * ProxyFactory.
 *
 * Creates the service instance of an API interface. The proxy code is generated
 * once and cached; its identifier changes with the interface file, so an edited
 * interface never meets a stale proxy. Creating a proxy builds no HTTP client:
 * the pipeline is only touched by the first call.
 *
 * @internal
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final readonly class ProxyFactory
{
    public function __construct(
        private ProxyStore $store,
        private EndpointInvoker $invoker,
        private ProxyGenerator $generator = new ProxyGenerator(),
    ) {}

    /**
     * @template T of object
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    public function create(string $interface): object
    {
        $reflection = new \ReflectionClass($interface);
        $identifier = self::identifier($reflection);
        $class = $identifier;
        if (!class_exists($class, false)) {
            if (!$this->store->has($identifier)) {
                $this->store->save($identifier, $this->generator->generate($reflection, $class));
            }
            $this->store->load($identifier);
        }

        /** @var T */
        return new $class($this->invoker);
    }

    /**
     * @param \ReflectionClass<object> $interface
     */
    public static function identifier(\ReflectionClass $interface): string
    {
        $file = $interface->getFileName();
        $fingerprint = is_string($file) ? filemtime($file).':'.filesize($file) : '';

        return 'OutboundProxy_'.$interface->getShortName().'_'.substr(sha1($interface->getName().':'.$fingerprint.':'.ProxyGenerator::VERSION), 0, 12);
    }
}
