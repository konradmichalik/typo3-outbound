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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Runtime;

use KonradMichalik\Typo3Outbound\Exception\ApiValidationFailed;
use KonradMichalik\Typo3Outbound\Outbound;
use KonradMichalik\Typo3Outbound\Proxy\FileProxyStore;
use KonradMichalik\Typo3Outbound\Runtime\ResultMapper;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, RecordingLogger};
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\{ContactLookupApi, ItemsApi};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../Fixtures/Definitions.php';
require_once __DIR__.'/../Fixtures/CommandDefinitions.php';

/**
 * ErrorLogTest.
 *
 * Error answers often echo what was sent, personal data included, so their body never reaches a log.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ResultMapper::class)]
final class ErrorLogTest extends TestCase
{
    #[Test]
    public function anErrorBodyIsNotLogged(): void
    {
        $logger = new RecordingLogger();
        $mock = new MockTransport();
        $mock->on(ItemsApi::class, 'find')->respondWithJson(['title' => 'Invalid', 'submitted' => ['email' => 'jane.doe@example.com']], 422);
        $api = Outbound::create([ItemsApi::class])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withLogger($logger)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(ItemsApi::class);

        try {
            $api->find(1);
            self::fail('A 422 is no item.');
        } catch (ApiValidationFailed) {
        }

        self::assertNotSame([], $logger->records);
        self::assertStringNotContainsString('jane.doe@example.com', json_encode($logger->records, \JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function theRequestLogShowsThePathTemplateInsteadOfTheValues(): void
    {
        $logger = new RecordingLogger();
        $mock = new MockTransport();
        $mock->on(ContactLookupApi::class, 'byEmail')->respondWithJson('found');
        $api = Outbound::create([ContactLookupApi::class])
            ->withTransport($mock)
            ->withClock(new FakeClock())
            ->withLogger($logger)
            ->withProxyStore(new FileProxyStore(sys_get_temp_dir().'/typo3-outbound-test-proxies'))
            ->api(ContactLookupApi::class);

        $api->byEmail('jane.doe@example.com', 'vip customer');

        $log = json_encode($logger->records, \JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('jane.doe', $log);
        self::assertStringNotContainsString('vip', $log);
        self::assertStringContainsString('\/contacts\/by-email\/{email}', $log);
    }
}
