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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Performance;

use KonradMichalik\OutboundPetstore\Api\PetApi;
use KonradMichalik\Typo3Outbound\Testing\MockTransport;
use KonradMichalik\Typo3Outbound\Tests\Support\{FakeClock, PetstoreKernel};
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;

use function sprintf;

/**
 * OverheadTest.
 *
 * Measures the time typo3_outbound adds to a call, without network: request
 * building, pipeline, decoding and mapping against the mock transport, and a
 * cache hit. Set OUTBOUND_BENCH_REPORT=1 to print the numbers.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class OverheadTest extends TestCase
{
    private const RUNS = 300;

    #[Test]
    public function overheadPerCallStaysBelowOneMillisecond(): void
    {
        $mock = new MockTransport();
        $mock->on(PetApi::class, 'findLater')->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');
        $mock->on(PetApi::class, 'find')->respondWithFixture(PetstoreKernel::RESPONSES.'/pet-10.json');
        $pets = PetstoreKernel::create($mock, new FakeClock())->api(PetApi::class);
        $pets->findLater(1)->get();
        $pets->find(1);

        $start = hrtime(true);
        for ($i = 0; $i < self::RUNS; ++$i) {
            $pets->findLater($i)->get();
        }
        $uncached = (hrtime(true) - $start) / self::RUNS / 1e6;

        $start = hrtime(true);
        for ($i = 0; $i < self::RUNS; ++$i) {
            $pets->find(1);
        }
        $cached = (hrtime(true) - $start) / self::RUNS / 1e6;

        if ('1' === getenv('OUTBOUND_BENCH_REPORT')) {
            fwrite(\STDERR, sprintf("\nuncached call incl. mock transport: %.3f ms, cache hit: %.3f ms\n", $uncached, $cached));
        }
        self::assertLessThan(1.0, $cached);
        self::assertLessThan(5.0, $uncached);
    }
}
