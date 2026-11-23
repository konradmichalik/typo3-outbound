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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Context;

use KonradMichalik\Typo3Outbound\Context\{StaticCallContext, ValueResolver};
use KonradMichalik\Typo3Outbound\Exception\MissingContext;
use KonradMichalik\Typo3Outbound\Value\{Env, Secret, SiteSetting};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * ValueResolverTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ValueResolver::class)]
final class ValueResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('OUTBOUND_TEST_EMPTY');
        putenv('OUTBOUND_TEST_SET');
    }

    #[Test]
    public function secretsAreReadFromTheReferencedEnvironmentVariable(): void
    {
        putenv('OUTBOUND_TEST_TOKEN=s3cret');
        $resolver = new ValueResolver(new StaticCallContext('main', ['crm.token' => 'env:OUTBOUND_TEST_TOKEN', 'crm.baseUri' => 'https://crm.example.com']));

        self::assertSame('s3cret', $resolver->resolve(new Secret('crm.token')));
        self::assertSame('https://crm.example.com', $resolver->resolve(new SiteSetting('crm.baseUri')));
        self::assertSame('s3cret', $resolver->resolve(new Env('OUTBOUND_TEST_TOKEN')));
    }

    #[Test]
    public function aSecretWrittenIntoSettingsIsRejected(): void
    {
        $this->expectExceptionCode(1794700054);
        (new ValueResolver(new StaticCallContext('main', ['crm.token' => 'plain-secret'])))->resolve(new Secret('crm.token'));
    }

    #[Test]
    public function anEnvironmentValueOfOnlyANewlineIsNotSet(): void
    {
        putenv("OUTBOUND_TEST_TOKEN=\n");

        $this->expectExceptionCode(1794700052);
        (new ValueResolver(new StaticCallContext()))->resolve(new Env('OUTBOUND_TEST_TOKEN'));
    }

    #[Test]
    public function aMissingVariableFallsBackToTheDefault(): void
    {
        putenv('OUTBOUND_TEST_UNSET');

        self::assertSame('https://api.example.com', (new ValueResolver(new StaticCallContext()))->resolve(new Env('OUTBOUND_TEST_UNSET', 'https://api.example.com')));
    }

    #[Test]
    public function anEmptyVariableFallsBackToTheDefault(): void
    {
        putenv('OUTBOUND_TEST_EMPTY=');

        self::assertSame('fallback', (new ValueResolver(new StaticCallContext()))->resolve(new Env('OUTBOUND_TEST_EMPTY', 'fallback')));
    }

    #[Test]
    public function aSetVariableWinsOverTheDefault(): void
    {
        putenv('OUTBOUND_TEST_SET=real');

        self::assertSame('real', (new ValueResolver(new StaticCallContext()))->resolve(new Env('OUTBOUND_TEST_SET', 'fallback')));
    }

    #[Test]
    public function siteSettingsNeedASite(): void
    {
        $this->expectException(MissingContext::class);
        (new ValueResolver(new StaticCallContext()))->resolve(new SiteSetting('crm.baseUri'));
    }
}
