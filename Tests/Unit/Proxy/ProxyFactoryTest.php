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

use KonradMichalik\Typo3Outbound\Proxy\ProxyFactory;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * ProxyFactoryTest.
 *
 * A proxy implements inherited methods too, so editing a parent interface must yield a new proxy.
 *
 * @author Konrad Michalik <hej@konradmichalik.dev>
 */
#[CoversClass(ProxyFactory::class)]
final class ProxyFactoryTest extends TestCase
{
    #[Test]
    public function editingAParentInterfaceChangesTheIdentifier(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $directory = sys_get_temp_dir().'/outbound-proxy-identifier-'.$suffix;
        mkdir($directory);
        file_put_contents($directory.'/Base.php', "<?php\ninterface IdentifierBase{$suffix} {}\n");
        file_put_contents($directory.'/Child.php', "<?php\ninterface IdentifierChild{$suffix} extends IdentifierBase{$suffix} {}\n");
        require $directory.'/Base.php';
        require $directory.'/Child.php';
        /** @var class-string $child */
        $child = 'IdentifierChild'.$suffix;
        $before = ProxyFactory::identifier(new ReflectionClass($child));

        file_put_contents($directory.'/Base.php', "\n// a method was added here\n", \FILE_APPEND);
        clearstatcache();

        self::assertNotSame($before, ProxyFactory::identifier(new ReflectionClass($child)));
    }
}
