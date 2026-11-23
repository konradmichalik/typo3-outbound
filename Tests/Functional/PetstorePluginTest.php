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

namespace KonradMichalik\Typo3Outbound\Tests\Functional;

use KonradMichalik\OutboundPetstore\Api\{PetApi, StoreApi};
use KonradMichalik\Typo3Outbound\Testing\OutboundTestTrait;
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * PetstorePluginTest.
 *
 * Renders the fixture plugin through the full TYPO3 stack (container build with
 * the compiler pass, middleware, Extbase, Fluid) with mocked Petstore endpoints.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversNothing]
final class PetstorePluginTest extends FunctionalTestCase
{
    use OutboundTestTrait;

    private const RESPONSES = __DIR__.'/Fixtures/Extensions/outbound_petstore/Resources/Private/Responses';

    protected array $testExtensionsToLoad = [
        'konradmichalik/typo3-outbound',
        __DIR__.'/Fixtures/Extensions/outbound_petstore',
    ];

    // Plugin actions are addressed by plain query parameters, without a cHash.
    protected array $configurationToUseInTestInstance = [
        'FE' => ['cacheHash' => ['enforceValidation' => false]],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__.'/Fixtures/pages.csv');
        $siteDirectory = Environment::getConfigPath().'/sites/petstore';
        GeneralUtility::mkdir_deep($siteDirectory);
        file_put_contents($siteDirectory.'/config.yaml', "rootPageId: 1\nbase: 'https://localhost/'\nlanguages:\n  - languageId: 0\n    title: English\n    locale: en_US.UTF-8\n    base: /\n");
        $this->setUpFrontendRootPage(1, ['EXT:outbound_petstore/Configuration/Sets/OutboundPetstore/setup.typoscript']);
    }

    #[Test]
    public function listRendersPetsFromTheApi(): void
    {
        $this->mockEndpoint(PetApi::class, 'findByStatus')->respondWithFixture(self::RESPONSES.'/pets-available.json');

        $html = (string) $this->executeFrontendSubRequest(new InternalRequest('https://localhost/'))->getBody();

        self::assertStringContainsString('doggie', $html);
        self::assertStringContainsString('whiskers', $html);
        $this->assertEndpointCalled(PetApi::class, 'findByStatus');
    }

    #[Test]
    public function inventoryRendersCounts(): void
    {
        $this->mockEndpoint(StoreApi::class, 'inventory')->respondWithFixture(self::RESPONSES.'/inventory.json');
        $request = (new InternalRequest('https://localhost/'))->withQueryParameters([
            'tx_outboundpetstore_pets[action]' => 'inventory',
            'tx_outboundpetstore_pets[controller]' => 'Pets',
        ]);

        $html = (string) $this->executeFrontendSubRequest($request)->getBody();

        self::assertStringContainsString('available', $html);
        $this->assertEndpointCalled(StoreApi::class, 'inventory');
    }

    #[Test]
    public function apiFailureIsRenderedAsError(): void
    {
        $this->mockEndpoint(PetApi::class, 'findByStatus')->respondWith(503);

        $html = (string) $this->executeFrontendSubRequest(new InternalRequest('https://localhost/'))->getBody();

        self::assertStringContainsString('ApiUnavailable', $html);
    }
}
