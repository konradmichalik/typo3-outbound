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

namespace KonradMichalik\Typo3Outbound\Tests\Support;

use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi, UserApi};
use KonradMichalik\Typo3Outbound\Context\SapiExecutionContext;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Transport\Transport;

/**
 * PetstoreKernel.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
final class PetstoreKernel
{
    public const RESPONSES = __DIR__.'/../Functional/Fixtures/Extensions/outbound_petstore/Resources/Private/Responses';

    public static function create(Transport $transport, FakeClock $clock, ?RecordingTagReporter $reporter = null, bool $web = true): Outbound
    {
        return Outbound::create([PetApi::class, StoreApi::class, UserApi::class])
            ->withTransport($transport)
            ->withClock($clock)
            ->withExecutionContext(new SapiExecutionContext($web))
            ->withCacheTagReporter($reporter ?? new RecordingTagReporter())
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'));
    }
}
