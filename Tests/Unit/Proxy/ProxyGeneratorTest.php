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

namespace KonradMichalik\Typo3Outbound\Tests\Unit\Proxy;

use KonradMichalik\OutboundPetstore\Api\PetApi;
use KonradMichalik\Typo3Outbound\Proxy\ProxyGenerator;
use KonradMichalik\Typo3Outbound\Tests\Unit\Fixtures\Proxy\{ConstantDefaultsApi, ConstantHolder};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * ProxyGeneratorTest.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ProxyGenerator::class)]
final class ProxyGeneratorTest extends TestCase
{
    #[Test]
    public function generatedCodeContainsNoAttributeValues(): void
    {
        $code = (new ProxyGenerator())->generate(new \ReflectionClass(PetApi::class), 'PetProxy');

        self::assertStringNotContainsString('petstore_pet_', $code);
        self::assertStringNotContainsString('/findByStatus', $code);
        self::assertStringContainsString('\\KonradMichalik\\OutboundPetstore\\Dto\\PetStatus $status = \\KonradMichalik\\OutboundPetstore\\Dto\\PetStatus::Available', $code);
        self::assertStringContainsString('public function delete(int $petId, ?string $apiKey = NULL): void', $code);
    }

    #[Test]
    public function invalidClassNamesAreRejected(): void
    {
        $this->expectExceptionCode(1794700160);
        (new ProxyGenerator())->generate(new \ReflectionClass(PetApi::class), 'Evil; system("x")');
    }

    #[Test]
    public function constantDefaultsAreEmittedAsCodeThatCompiles(): void
    {
        $code = (new ProxyGenerator())->generate(new \ReflectionClass(ConstantDefaultsApi::class), 'ConstantsProxy');

        self::assertStringContainsString('int $limit = \\'.ConstantDefaultsApi::class.'::MAX', $code);
        self::assertStringContainsString('int $cap = \\PHP_INT_MAX', $code);
        self::assertStringContainsString('int $size = \\PHP_INT_SIZE', $code);
        self::assertStringContainsString('int $other = \\'.ConstantHolder::class.'::LIMIT', $code);
        self::assertStringNotContainsString('\\self::', $code);
    }

    #[Test]
    public function aGeneratedProxyWithConstantDefaultsCanBeLoaded(): void
    {
        $file = sys_get_temp_dir().'/outbound-proxy-lint-'.bin2hex(random_bytes(4)).'.php';
        file_put_contents($file, (new ProxyGenerator())->generate(new \ReflectionClass(ConstantDefaultsApi::class), 'ConstantsProxy'));

        try {
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file).' 2>&1', $output, $status);
            self::assertSame(0, $status, implode("\n", $output));
            exec(escapeshellarg(PHP_BINARY).' -r '.escapeshellarg('interface_exists('.var_export(ConstantDefaultsApi::class, true).') || require '.var_export(__DIR__.'/../Fixtures/Proxy/ConstantDefaultsApi.php', true).'; require '.var_export($file, true).'; echo "loaded";').' 2>&1', $loadOutput, $loadStatus);
            self::assertSame(0, $loadStatus, implode("\n", $loadOutput));
        } finally {
            unlink($file);
        }
    }
}
