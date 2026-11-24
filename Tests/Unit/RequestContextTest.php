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

namespace KonradMichalik\Typo3Outbound\Tests\Unit;

use KonradMichalik\Outbound\Exception\MissingContext;
use KonradMichalik\Typo3Outbound\Context\RequestContext;
use LogicException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Http\{ServerRequest, Uri};
use TYPO3\CMS\Core\Site\Entity\{Site, SiteLanguage};
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * RequestContextTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(RequestContext::class)]
final class RequestContextTest extends TestCase
{
    #[Test]
    public function withoutRequestOrScopeThereIsNeitherScopeNorLanguage(): void
    {
        $context = $this->context([]);

        self::assertNull($context->request());
        self::assertNull($context->scope());
        self::assertNull($context->language());
    }

    #[Test]
    public function theSiteOfTheCurrentRequestIsTheScope(): void
    {
        $context = $this->context([]);
        $context->setRequest($this->request($this->site('petstore')));

        self::assertSame('petstore', $context->scope());
    }

    #[Test]
    public function aSiteAttributeThatIsNotASiteIsIgnored(): void
    {
        $context = $this->context([]);
        $context->setRequest((new ServerRequest('https://localhost/'))->withAttribute('site', 'petstore'));

        self::assertNull($context->scope());
    }

    #[Test]
    public function theLanguageIsReportedAsBcp47Tag(): void
    {
        $context = $this->context([]);
        $request = $this->request($this->site('petstore'))->withAttribute('language', new SiteLanguage(1, 'de_AT.UTF-8', new Uri('/'), []));
        $context->setRequest($request);

        self::assertSame('de-AT', $context->language());
    }

    #[Test]
    public function aLanguageAttributeThatIsNotASiteLanguageIsIgnored(): void
    {
        $context = $this->context([]);
        $context->setRequest((new ServerRequest('https://localhost/'))->withAttribute('language', 'de'));

        self::assertNull($context->language());
    }

    #[Test]
    public function aScopedSiteWinsOverTheRequestAndIsRestoredAfterwards(): void
    {
        $context = $this->context(['store' => $this->site('store')]);
        $context->setRequest($this->request($this->site('petstore')));

        $inside = $context->withSite('store', static fn (): ?string => $context->scope());

        self::assertSame('store', $inside);
        self::assertSame('petstore', $context->scope());
    }

    #[Test]
    public function nestedScopesRestoreTheOuterOne(): void
    {
        $context = $this->context(['a' => $this->site('a'), 'b' => $this->site('b')]);

        $seen = $context->withSite('a', static function () use ($context): array {
            $inner = $context->withSite('b', static fn (): ?string => $context->scope());

            return [$inner, $context->scope()];
        });

        self::assertSame(['b', 'a'], $seen);
        self::assertNull($context->scope());
    }

    #[Test]
    public function theScopeIsRestoredWhenTheCallbackThrows(): void
    {
        $context = $this->context(['store' => $this->site('store')]);

        $this->expectException(LogicException::class);

        try {
            $context->withSite('store', static function (): void {
                throw new LogicException('boom', 5576821090);
            });
        } finally {
            self::assertNull($context->scope());
        }
    }

    #[Test]
    public function runInScopeRunsTheCallbackInTheSiteOfThatName(): void
    {
        $context = $this->context(['store' => $this->site('store')]);

        self::assertSame('store', $context->runInScope('store', static fn (): ?string => $context->scope()));
    }

    #[Test]
    public function aSiteSettingIsReadFromTheCurrentSite(): void
    {
        $context = $this->context([]);
        $context->setRequest($this->request($this->site('petstore', ['petstore' => ['baseUrl' => 'https://petstore3.swagger.io']])));

        self::assertSame('https://petstore3.swagger.io', $context->setting('petstore.baseUrl'));
    }

    #[Test]
    public function aSettingWithoutASiteExplainsHowToSelectOne(): void
    {
        $this->expectException(MissingContext::class);
        $this->expectExceptionCode(1794700190);
        $this->expectExceptionMessage('"petstore.baseUrl"');

        $this->context([])->setting('petstore.baseUrl');
    }

    /**
     * @param array<string, Site> $sites
     */
    private function context(array $sites): RequestContext
    {
        $finder = $this->createMock(SiteFinder::class);
        $finder->method('getSiteByIdentifier')->willReturnCallback(static fn (string $identifier): Site => $sites[$identifier]);

        return new RequestContext($finder);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function site(string $identifier, array $settings = []): Site
    {
        return new Site($identifier, 1, ['base' => 'https://localhost/', 'languages' => [], 'settings' => $settings]);
    }

    private function request(Site $site): ServerRequest
    {
        return (new ServerRequest('https://localhost/'))->withAttribute('site', $site);
    }
}
